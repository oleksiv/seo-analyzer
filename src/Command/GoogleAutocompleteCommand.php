<?php

namespace App\Command;

use App\Entity\Keyword;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class GoogleAutocompleteCommand extends Command
{
    protected static $defaultName = 'google-autocomplete';
    const GOOGLE_AUTOCOMPLETE_URL = 'http://suggestqueries.google.com/complete/search';
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
            ->addArgument('keyword', InputArgument::REQUIRED, 'Define a keyword')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $keyword = $input->getArgument('keyword');
        $this->searchAlphabet(rawurlencode(' ' . $keyword), $input);
    }

    public function searchAlphabet($keyword, InputInterface $input)
    {
        $client = new Client([
            // Base URI is used with relative requests
            'base_uri' => self::GOOGLE_AUTOCOMPLETE_URL,
            // You can set any number of default request options.
            'timeout'  => 2.0,
        ]);

        $alphas = range('a', 'z');

        foreach ($alphas as $alpha) {

            $url = sprintf('?client=psy-ab&hl=en&q=%s %s', $alpha, $keyword);
            $keywords = $this->getKeywords($client, $url);
            foreach ($keywords as $k) {
                $_keyword = strip_tags(html_entity_decode($k[0], ENT_QUOTES | ENT_XML1, 'UTF-8'));
                $this->setKeywords($this->em, $_keyword);
            }

            $url = sprintf('?client=psy-ab&hl=en&q=%s %s', $keyword, $alpha);
            $keywords = $this->getKeywords($client, $url);
            foreach ($keywords as $k) {
                $_keyword = strip_tags(html_entity_decode($k[0], ENT_QUOTES | ENT_XML1, 'UTF-8'));
                $this->setKeywords($this->em, $_keyword);
            }
        }

        $numbers = range(0, 9);
        foreach ($numbers as $number) {
            $url = sprintf('?client=psy-ab&hl=en&q=%s %s', $number, $keyword);
            $keywords = $this->getKeywords($client, $url);
            foreach ($keywords as $k) {
                $_keyword = strip_tags(html_entity_decode($k[0], ENT_QUOTES | ENT_XML1, 'UTF-8'));
                $this->setKeywords($this->em, $_keyword);
            }

            $url = sprintf('?client=psy-ab&hl=en&q=%s %s', $keyword, $number);
            $keywords = $this->getKeywords($client, $url);
            foreach ($keywords as $k) {
                $_keyword = strip_tags(html_entity_decode($k[0], ENT_QUOTES | ENT_XML1, 'UTF-8'));
                $this->setKeywords($this->em, $_keyword);
            }
        }
    }

    public function setKeywords(EntityManagerInterface $entityManager, $keyword)
    {
        /** @var Keyword $entity */
        $entity = $entityManager->getRepository(Keyword::class)->findOneBy(array('keyword' => $keyword));
        if(empty($entity)) $entity = new Keyword();
        $entity->setKeyword($keyword);
        $entityManager->persist($entity);
        $entityManager->flush();
    }

    public function getKeywords(Client $client, $url)
    {
        $response = $client->request('GET', $url);
        $decoded = json_decode($response->getBody());
        return isset($decoded[1]) ? $decoded[1] : array();
    }

    public function writeToFile($filename, $keyword)
    {
        $file_url = __DIR__ . '/../../results/' . urlencode($filename) . '.txt';
        if(!file_exists($file_url)) {
            file_put_contents($file_url, null);
        }
        $contents = file_get_contents($file_url);
        $contents = $contents . $keyword . PHP_EOL;
        file_put_contents($file_url, $contents);
    }

    /**
     * @param $keyword
     * @param $current_depth
     * @param $depth
     * @param InputInterface $input
     */
    protected function searchRecursive($keyword, $current_depth, $depth, InputInterface $input) {

        $client = new Client([
            // Base URI is used with relative requests
            'base_uri' => self::GOOGLE_AUTOCOMPLETE_URL,
            // You can set any number of default request options.
            'timeout'  => 2.0,
        ]);
        $url = sprintf('?client=psy-ab&hl=en&q=%s', $keyword);
        $response = $client->request('GET', $url);
        $decoded = json_decode($response->getBody());
        $items = isset($decoded[1]) ? $decoded[1] : array();

        foreach ($items as $item) {
            $_keyword = strip_tags(html_entity_decode($item[0], ENT_QUOTES | ENT_XML1, 'UTF-8'));
            $content = str_repeat("\t", $current_depth) . $_keyword . PHP_EOL;
            echo $content;

            $file_url = __DIR__ . '/../../results/' . urlencode($input->getArgument('keyword')) . '.txt';
            if(!file_exists($file_url)) {
                file_put_contents($file_url, null);
            }
            $contents = file_get_contents($file_url);
            $contents = $contents . str_repeat("\t", $current_depth) . $_keyword . PHP_EOL;
            file_put_contents($file_url, $contents);

            if($current_depth < $depth) {
                $this->searchRecursive(rawurlencode($_keyword), $current_depth + 1, $depth, $input);
            }
        }
    }
}
