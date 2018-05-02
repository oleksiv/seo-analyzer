<?php

namespace App\Command;

use App\Entity\Keyword;
use App\Entity\SearchResult;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use GuzzleHttp\Client;
use SEOstats\Services\Google;
use Sunra\PhpSimple\HtmlDomParser;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class GoogleSearchCommand extends Command
{
    protected static $defaultName = 'GoogleSearch';
    const PAGE_LIMIT = 50;
    protected $em;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->em = $entityManager;
    }

    protected function configure()
    {
        $this
            ->setDescription('Add a short description for your command')
            ->addArgument('keyword', InputArgument::OPTIONAL, 'Argument description')
            ->addOption('option1', null, InputOption::VALUE_NONE, 'Option description')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Get database keywords list
        $keywords = $this->em->getRepository(Keyword::class)
            ->createQueryBuilder('k')
            ->getQuery()
            ->getResult()
        ;
        $client = new Client(array(
            'timeout' => 10
        ));
        /** @var Keyword $keyword */
        foreach ($keywords as $keyword) {
            $url = sprintf('https://www.google.com.ua/search?q=%s', $keyword->getKeyword());
            $response = $client->request('GET', $url);
            $html = $response->getBody();
            $dom = HtmlDomParser::str_get_html($html);
            $items = $dom->find('#ires > ol > .g');

            // Remove keywords
            $keyword_entity = $this->em->getRepository(Keyword::class)->findOneBy(
                array(
                    'keyword' => $keyword->getKeyword()
                )
            );
            $entities = $this->em->getRepository(SearchResult::class)->findBy(array(
                'keyword' => $keyword_entity
            ));
            foreach ($entities as $entity) {
                // Delete from database
                $this->em->remove($entity);
                $this->em->flush();
            }

            foreach ($items as $item) {
                try {
                    $href = htmlspecialchars_decode($item->find('h3 > a', 0)->attr['href']);
                    $params = array();
                    parse_str('&' . parse_url($href)['query'], $params);
                    // Web Site url
                    $href = $params['q'];
                    // request for web site content

                    $web_site = $client->request('GET', $href);
                    $web_site_html = $web_site->getBody();
                    // Dom
                    $web_site_dom = HtmlDomParser::str_get_html($web_site_html);
                    //
                    if(empty($web_site_dom)) {
                        echo 'Error getting website ' . $href . PHP_EOL;
                        continue;
                    }
                    // Title
                    $web_site_title = empty($web_site_dom->find('title', 0)) ? null : $web_site_dom->find('title', 0)->text();
                    // Description
                    $web_site_description = empty($web_site_dom->find('meta[name=description]', 0)) ? null : $web_site_dom->find('meta[name="description"]', 0)->attr['content'];
                    // H1 tag
                    $web_site_h1 = empty($web_site_dom->find('h1', 0)) ? null : $web_site_dom->find('h1', 0)->text();

                    // Create new
                    $new = new SearchResult();
                    $new->setKeyword($keyword_entity);
                    $new->setUrl($href);
                    $new->setTitle($web_site_title);
                    $new->setDescription($web_site_description);
                    $new->setH1Tag($web_site_h1);
                    // Write to database
                    $this->em->persist($new);
                    $this->em->flush();
                    echo $href . PHP_EOL;

//                    // Calculate complexity
//                    $current_complexity = 0;
//                    $current_complexity = stripos($web_site_title, $keyword) === false ? $current_complexity : $current_complexity + 100 / 3;
//                    $current_complexity = stripos($web_site_description, $keyword) === false ? $current_complexity : $current_complexity + 100 / 3;
//                    $current_complexity = stripos($web_site_h1, $keyword) === false ? $current_complexity : $current_complexity + 100 / 3;
//                    $complexity = $complexity + $current_complexity;
//                    echo $keyword . ' - ' . substr($href, 0, 30) . ' - ' . ceil($current_complexity) . '%' . PHP_EOL;

                } catch (\Exception $exception) {
                    echo 'Error cannot resolve host ' . $href . PHP_EOL;
                }
            }
        }
    }
}
