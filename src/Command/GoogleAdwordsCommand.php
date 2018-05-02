<?php

namespace App\Command;

use App\Entity\Keyword;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Google\AdsApi\AdWords\AdWordsServices;
use Google\AdsApi\AdWords\AdWordsSession;
use Google\AdsApi\AdWords\AdWordsSessionBuilder;
use Google\AdsApi\AdWords\v201802\cm\Language;
use Google\AdsApi\AdWords\v201802\cm\NetworkSetting;
use Google\AdsApi\AdWords\v201802\cm\Paging;
use Google\AdsApi\AdWords\v201802\o\AttributeType;
use Google\AdsApi\AdWords\v201802\o\IdeaType;
use Google\AdsApi\AdWords\v201802\o\LanguageSearchParameter;
use Google\AdsApi\AdWords\v201802\o\NetworkSearchParameter;
use Google\AdsApi\AdWords\v201802\o\RelatedToQuerySearchParameter;
use Google\AdsApi\AdWords\v201802\o\RequestType;
use Google\AdsApi\AdWords\v201802\o\TargetingIdeaSelector;
use Google\AdsApi\AdWords\v201802\o\TargetingIdeaService;
use Google\AdsApi\Common\OAuth2TokenBuilder;
use Google\AdsApi\Common\Util\MapEntries;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

//

class GoogleAdwordsCommand extends Command
{
    protected static $defaultName = 'GoogleAdwords';
    const AD_GROUP_ID = null;
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
            ->addArgument('arg1', InputArgument::OPTIONAL, 'Argument description')
            ->addOption('option1', null, InputOption::VALUE_NONE, 'Option description');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $arg1 = $input->getArgument('arg1');

        if ($arg1) {
            $io->note(sprintf('You passed an argument: %s', $arg1));
        }

        if ($input->getOption('option1')) {
            // ...
        }

        // Generate a refreshable OAuth2 credential for authentication.
        $oAuth2Credential = (new OAuth2TokenBuilder())->fromFile()->build();
        // Construct an API session configured from a properties file and the
        // OAuth2 credentials above.
        $session = (new AdWordsSessionBuilder())->fromFile()->withOAuth2Credential($oAuth2Credential)->build();
        $this->runExample(new AdWordsServices(), $session);
    }

    public function runExample(AdWordsServices $adWordsServices, AdWordsSession $session)
    {
        /** @var TargetingIdeaService $targetingIdeaService */
        $targetingIdeaService = $adWordsServices->get($session, TargetingIdeaService::class);
        // Create selector.
        $selector = new TargetingIdeaSelector();
        $selector->setRequestType(RequestType::STATS);
        $selector->setIdeaType(IdeaType::KEYWORD);
        $selector->setRequestedAttributeTypes(
            array(
                AttributeType::KEYWORD_TEXT,
                AttributeType::SEARCH_VOLUME,
                AttributeType::AVERAGE_CPC,
                AttributeType::COMPETITION,
                AttributeType::CATEGORY_PRODUCTS_AND_SERVICES
            )
        );

        // Get database keywords list
        $query = $this->em->getRepository(Keyword::class)
            ->createQueryBuilder('k')
            ->getQuery()
        ;

        // Load doctrine Paginator
        $paginator = new Paginator($query);
        // Get total items
        $totalItems = count($paginator);
        // Get total pages
        $pagesCount = ceil($totalItems / self::PAGE_LIMIT);

        for($p = 0; $p < $pagesCount; $p++) {
            // Google AdWords paginator
            $paging = new Paging();
            $paging->setStartIndex($p);
            $paging->setNumberResults(self::PAGE_LIMIT);
            $selector->setPaging($paging);
            // Doctrine paginator
            $paginator
                ->getQuery()
                ->setFirstResult(self::PAGE_LIMIT * ($p))
                ->setMaxResults(self::PAGE_LIMIT)
            ;
            // Collect keywords
            $_keywords = array();
            foreach ($paginator as $item) {
                $_keywords[] = $item->getKeyword();
            }
            // Search parameters
            $searchParameters = [];
            // Create related to query search parameter.
            $relatedToQuerySearchParameter = new RelatedToQuerySearchParameter();
            $relatedToQuerySearchParameter->setQueries($_keywords);
            // Add parameter
            $searchParameters[] = $relatedToQuerySearchParameter;
            // Create language search parameter (optional).
            $languageParameter = new LanguageSearchParameter();
            $english = new Language();
            $english->setId(1000);
            $languageParameter->setLanguages([$english]);
            // Add parameter
            $searchParameters[] = $languageParameter;
            // Create network search parameter (optional).
            $networkSetting = new NetworkSetting();
            $networkSetting->setTargetGoogleSearch(true);
            $networkSetting->setTargetSearchNetwork(false);
            $networkSetting->setTargetContentNetwork(false);
            $networkSetting->setTargetPartnerSearchNetwork(false);
            $networkSearchParameter = new NetworkSearchParameter();
            $networkSearchParameter->setNetworkSetting($networkSetting);
            // Add parameter
            $searchParameters[] = $networkSearchParameter;
            $selector->setSearchParameters($searchParameters);
            $selector->setPaging(new Paging(0, self::PAGE_LIMIT));
            // Get keyword ideas.
            $page = $targetingIdeaService->get($selector);
            // Print out some information for each targeting idea.
            $entries = $page->getEntries();
            if ($entries !== null) {
                foreach ($entries as $targetingIdea) {
                    $data = MapEntries::toAssociativeArray($targetingIdea->getData());
                    $keyword = $data[AttributeType::KEYWORD_TEXT]->getValue();
                    $searchVolume = ($data[AttributeType::SEARCH_VOLUME]->getValue() !== null) ? $data[AttributeType::SEARCH_VOLUME]->getValue() : 0;
                    $averageCpc = $data[AttributeType::AVERAGE_CPC]->getValue();
                    $competition = $data[AttributeType::COMPETITION]->getValue();
                    $categoryIds = ($data[AttributeType::CATEGORY_PRODUCTS_AND_SERVICES]->getValue() === null) ? $categoryIds = '' : implode(', ', $data[AttributeType::CATEGORY_PRODUCTS_AND_SERVICES]->getValue());

                    /** @var Keyword $entity */
                    $entity = $this->em->getRepository(Keyword::class)->findOneBy(array('keyword' => $keyword));
                    if(empty($entity)) $entity = new Keyword();
                    $entity->setKeyword($keyword);
                    $entity->setVolume($searchVolume);
                    $entity->setCpc(($averageCpc === null) ? 0 : $averageCpc->getMicroAmount());
                    $entity->setCompetition($competition);
                    // Write to the database
                    $this->em->persist($entity);
                    $this->em->flush();
                    echo $keyword . ' - processed' . PHP_EOL;
                }
            }
            echo '=================================================' . PHP_EOL;
            sleep(60);
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
}
