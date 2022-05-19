<?php

/**
 * @file plugins/importexport/rsciexport/filter/ArticleRSCIXmlFilter.inc.php
 * @class ArticleRSCIXmlFilter
 * @ingroup plugins_importexport_rsciexport
 *
 * @brief Class that converts an Issue to a RSCI XML document.
 */

import('lib.pkp.classes.filter.PersistableFilter');
use Matriphe\ISO639\ISO639;

class ArticleRSCIXmlFilter extends PersistableFilter {

    /**
	 * Constructor
	 * @param $filterGroup FilterGroup
	 */
	function __construct($filterGroup) {
		parent::__construct($filterGroup);
	}


	//
	// Implement template methods from PersistableFilter
	//
	/**
	 * @copydoc PersistableFilter::getClassName()
	 */
	function getClassName() {
		return 'plugins.importexport.rsciexport.filter.ArticleRSCIXmlFilter';
	}

	//
	// Implement template methods from Filter
	//
	/**
	 * @see Filter::process()
	 * @param $issue Issue
	 * @return DOMDocument|false
	 */
	function &process(&$issue)
    {
        $doc = new DOMDocument('1.0', 'utf-8');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;

        $locales = AppLocale::getSupportedLocales();
		$langs = array();

        foreach ($locales as $locale=>$_)
        {
            $langs[] = $this->_convertLocaleToISO639($locale);
        }
        $doc->appendChild($this->_createJournalNode($doc, Application::getRequest()->getJournal(), $issue, $langs));

        return $doc;
	}


    /**
     * Construct and return journal node and nested nodes.
     * @param $doc DOMDocument
     * @param $journal Journal
     * @param $issue Issue
     * @param $langs string[]
     * @return DOMElement|false
     */
    protected function _createJournalNode($doc, $journal, $issue, $langs)
    {
        $journalNode = $doc->createElement('journal');
        $journalNode->appendChild($doc->createElement('titleid', $this->_exportSettings['journalRSCITitleId']));

        $issn = $journal->getData('printIssn');
        if ($issn != '')
        $journalNode->appendChild($doc->createElement('issn', $issn));

        $essn = $journal->getData('onlineIssn');
        if ($essn != '')
            $journalNode->appendChild($doc->createElement('essn', $essn));

        $primaryLocale = AppLocale::getPrimaryLocale();
        $journalNode->appendChild($this->_createJournalInfoNode($doc, $journal, $primaryLocale));

        $journalNode->appendChild($this->_createIssueNode($doc, $issue, $langs));

        return $journalNode;
    }

    /**
     * @param $doc DOMDocument
     * @param $journal Journal
     * @param $locale string
     * @return DOMElement|false
     */
    protected function _createJournalInfoNode($doc, $journal, $locale)
    {
        $journalInfoNode = $doc->createElement('journalInfo');
        $journalInfoNode->setAttribute('lang', $this->_convertLocaleToISO639($locale));
        $journalInfoNode->appendChild($doc->createElement('title', htmlentities($journal->getName($locale), ENT_XML1)));

        return $journalInfoNode;
    }

    /**
     * @param $doc DOMDocument
     * @param $issue Issue
     * @param $langs string[]
     * @return DOMElement|false
     */
    protected function _createIssueNode($doc, $issue, $langs)
    {
        $issueNode = $doc->createElement('issue');
        $issueNode->appendChild($doc->createElement('volume', $issue->getVolume()));
        $issueNode->appendChild($doc->createElement('number', $issue->getNumber()));
        //$issueNode->appendChild($doc->createElement('altNumber', ''));
        $issueNode->appendChild($doc->createElement('pages', $this->_getIssuePages($issue)));
        $issueNode->appendChild($doc->createElement('dateUni', $issue->getYear()));

        $filesNode = $doc->createElement('files');
        $coverFileNode = $doc->createElement('file', $issue->getLocalizedCoverImage());
        $coverFileNode->setAttribute('desc', 'cover');
        $filesNode->appendChild($coverFileNode);
        $issueNode->appendChild($filesNode);
        $issueNode->appendChild($this->_createArticlesNode($doc, $issue, $langs));

        return $issueNode;
    }

    /**
     * @param $doc DOMDocument
     * @param $issue Issue
     * @param $langs string[]
     * @return DOMElement|false
     */
    protected function _createArticlesNode($doc, $issue, $langs)
    {
        $articlesNode = $doc->createElement('articles');
        $publishedArticleDao = DAORegistry::getDAO('PublishedArticleDAO');
        $publishedArticles = $publishedArticleDao->getPublishedArticles($issue->getId());
        $publishedArticles = $this->_sortArticlesByPages($publishedArticles);
        $lastSectionId = null;

        foreach ($publishedArticles as $article)
        {
            if ($this->_exportSettings['isExportSections'] && ($lastSectionId !== $article->getSectionId()))
            {
                $articlesNode->appendChild($this->_createSectionNode($doc, $article, $langs));
            }

            $articlesNode->appendChild($this->_createArticleNode($doc, $article, $langs));
            $lastSectionId = $article->getSectionId();
        }

        return $articlesNode;
    }

    /**
     * @param $doc DOMDocument
     * @param $article PublishedArticle
     * @param $langs string[]
     * @return DOMElement|false
     */
    protected function _createArticleNode($doc, $article, $langs)
    {
        $submission = Application::getSubmissionDAO()->getById($article->getId());

        $articleNode = $doc->createElement('article');
        $articleNode->appendChild($doc->createElement('pages', $submission->getPages()));

        $sectionDAO = DAORegistry::getDAO('SectionDAO');
        $section = $sectionDAO->getById($article->getSectionId());
        $sectionAbbrev = $section->getAbbrev(AppLocale::getPrimaryLocale());

        if ($this->_exportSettings['isExportArtTypeFromSectionAbbrev'] && in_array($sectionAbbrev, $this->_artTypes))
        {
            $articleNode->appendChild($doc->createElement('artType', $sectionAbbrev));
        }
        else
        {
            $articleNode->appendChild($doc->createElement('artType', 'RAR'));
        }

        $authorsNode = $doc->createElement('authors');
        $authors = $submission->getAuthors();
        $authorCount = 1;
        foreach ($authors as $author)
        {
            $authorsNode->appendChild($this->_createAuthorNode($doc, $author, $authorCount, $langs));
            $authorCount++;
        }
        $articleNode->appendChild($authorsNode);

        $articleNode->appendChild($this->_createArtTitlesNode($doc, $article, $langs));
        $articleNode->appendChild($this->_createAbstractsNode($doc, $article, $langs));

        //$articleNode->appendChild();    // TODO: create <text>

        $articleNode->appendChild($this->_createCodesNode($doc, $article));
        $articleNode->appendChild($this->_createKeywordsNode($doc, $article, $langs));
        $articleNode->appendChild($this->_createReferencesNode($doc, $article));
        $articleNode->appendChild($this->_createFilesNode($doc, $article));

        return $articleNode;
    }

    protected $_artTypes = array ('ABS', 'BRV', 'CNF', 'COR', 'EDI', 'MIS', 'PER', 'RAR', 'REP', 'REV', 'SCO', 'UNK');

    /**
     * @param $doc DOMDocument
     * @param $author Author
     * @param $authorNumber int Serial number of the author in the article authors list
     * @param $langs string[]
     * @return DOMElement|false
     */
    protected function _createAuthorNode($doc, $author, $authorNumber, $langs)
    {
        $authorNode = $doc->createElement('author');
        $authorNode->setAttribute('num', str_pad(strval($authorNumber), 3, '0', STR_PAD_LEFT));

        foreach ($langs as $lang)
        {
            $individInfoNode = $doc->createElement('individInfo');
            $individInfoNode->setAttribute('lang', $lang);

            $locale = $this->_convertISO639ToLocale($lang);

            $individInfoNode->appendChild($doc->createElement('surname', $author->getFamilyName($locale)));
            $individInfoNode->appendChild($doc->createElement('initials', $this->_createInitials($author->getGivenName($locale))));

            $affiliation = $this->_parseAffiliation($author->getAffiliation($locale));
            $individInfoNode->appendChild($doc->createElement('orgName', htmlentities($affiliation->Organization, ENT_XML1)));
            $individInfoNode->appendChild($doc->createElement('address', htmlentities($affiliation->Address, ENT_XML1)));

            if($author->getEmail() != "null@null.null")
                $individInfoNode->appendChild($doc->createElement('email', $author->getEmail()));

            $authorNode->appendChild($individInfoNode);
        }

        if($author->getOrcid() != '')
        {
            $authorCodesNode = $doc->createElement('authorCodes');
            $orcidParts = explode('/', $author->getOrcid());
            $orcid = end($orcidParts);
            $authorCodesNode->appendChild($doc->createElement('orcid', $orcid));
            $authorNode->appendChild($authorCodesNode);
        }

        return $authorNode;
    }

    /**
     * @param $doc DOMDocument
     * @param $article Article
     * @param $langs string[]
     * @return DOMElement|false
     */
    protected function _createArtTitlesNode($doc, $article, $langs)
    {
        $artTitlesNode = $doc->createElement('artTitles');

        foreach ($langs as $lang)
        {
            $locale = $this->_convertISO639ToLocale($lang);
            $artTitleNode = $doc->createElement('artTitle', htmlentities($article->getTitle($locale, false), ENT_XML1));
            $artTitleNode->setAttribute('lang', $lang);
            $artTitlesNode->appendChild($artTitleNode);
        }

        return $artTitlesNode;
    }

    /**
     * @param $doc DOMDocument
     * @param $article Article
     * @param $langs string[]
     * @return DOMElement|false
     */
    protected function _createAbstractsNode($doc, $article, $langs)
    {
        $abstractsNode = $doc->createElement('abstracts');

        foreach($langs as $lang)
        {
            $locale = $this->_convertISO639ToLocale($lang);
            $abstractNode = $doc->createElement('abstract', htmlentities(strip_tags($article->getAbstract($locale)), ENT_XML1));
            $abstractNode->setAttribute('lang', $lang);
            $abstractsNode->appendChild($abstractNode);
        }

        return $abstractsNode;
    }

    /**
     * @param $doc DOMDocument
     * @param $article Article
     * @return DOMElement|false
     */
    protected function _createCodesNode($doc, $article)
    {
        $codesNode = $doc->createElement('codes');
        $codesNode->appendChild($doc->createElement('doi', $article->getStoredPubId('doi')));
        return $codesNode;
    }

    /**
     * @param $doc DOMDocument
     * @param $article Article
     * @param $langs string[]
     * @return DOMElement|false
     */
    protected function _createKeywordsNode($doc, $article, $langs)
    {
        $keywordsNode = $doc->createElement('keywords');
        $submissionKeywordsDAO = DAORegistry::getDAO('SubmissionKeywordDAO');

        foreach ($langs as $lang)
        {
            $kwdGroup = $doc->createElement('kwdGroup');
            $kwdGroup->setAttribute('lang', $lang);
            $locale = $this->_convertISO639ToLocale($lang);
            $keywords = $submissionKeywordsDAO->getKeywords($article->getId(), array($locale))[$locale];

            foreach ($keywords as $locale=>$keyword)
            {
                $kwdGroup->appendChild($doc->createElement('keyword', htmlentities($keyword, ENT_XML1)));
            }
            $keywordsNode->appendChild($kwdGroup);
        }
        return $keywordsNode;
    }

    /**
     * @param $doc DOMDocument
     * @param $article Article
     * @return DOMElement|false
     */
    protected function _createReferencesNode($doc, $article)
    {
        $referencesNode = $doc->createElement('references');
        $citationDAO = DAORegistry::getDAO('CitationDAO');
        $citations = $citationDAO->getBySubmissionId($article->getId());
        $lang = $this->_convertLocaleToISO639(AppLocale::getPrimaryLocale());

        for ($i = 0; $i < $citations->getCount(); $i++)
        {
            $citation = $citations->next();
            $referenceNode = $doc->createElement('reference');
            $refInfoNode = $doc->createElement('refInfo');
            $refInfoNode->setAttribute('lang', $lang);
            $textNode = $doc->createElement('text', htmlentities($citation->getRawCitation(), ENT_XML1));
            $refInfoNode->appendChild($textNode);
            $referenceNode->appendChild($refInfoNode);
            $referencesNode->appendChild($referenceNode);
        }

        return $referencesNode;
    }

    /**
     * @param $doc DOMDocument
     * @param $article Article
     * @return DOMElement|false
     */
    protected function _createFilesNode($doc, $article)
    {
        $filesNode = $doc->createElement('files');

        $articleGalleyDAO = DAORegistry::getDAO('ArticleGalleyDAO');
        $galley = $articleGalleyDAO->getBySubmissionId($article->getId())->next();

        $request = Application::getRequest();
        $context = $request->getRouter()->getContext($request);
        $resourceURL = $request->url($context->getPath(), 'article', 'view', $article->getBestArticleId(), null, null, true);
        $furlNode = $doc->createElement('furl', $resourceURL);
        $furlNode->setAttribute('location', 'publisher');
        $furlNode->setAttribute('version', 'published');
        $filesNode->appendChild($furlNode);

        $fileNode = $doc->createElement('file', $this->getArticleFileNameToRSCI($article));
        $fileNode->setAttribute('desc', 'fullText');
        $filesNode->appendChild($fileNode);

        return $filesNode;
    }

    /**
     * Converts galley filename to RSCI name format for archiving (example: "13-16.pdf").
     * @param $article Article
     * @return string
     */
    static public function getArticleFileNameToRSCI($article)
    {
        $articleGalleyDAO = DAORegistry::getDAO('ArticleGalleyDAO');
        $galley = $articleGalleyDAO->getBySubmissionId($article->getId())->next();
        $fileName = $galley->getFile()->getName(AppLocale::getPrimaryLocale());
        return explode(' ', $fileName)[1];
    }

    /**
     * @param $affiliationStr string
     * @return Affiliation
     */
    protected function _parseAffiliation($affiliationStr)
    {
        $affiliationsStr = explode(';', $affiliationStr);

        $organizations = array();
        $addresses = array();
        foreach ($affiliationsStr as $affiliation)
        {
            $affiliation = trim($affiliation);
            $address = '';
            $organization = $affiliation;

            $lastComma = mb_strrpos($affiliation, ',');
            if ($lastComma !== false) {
                $next_to_last = mb_strrpos($affiliation, ',', $lastComma - mb_strlen($affiliation) - 1);
                $organization = mb_substr($affiliation, 0, $next_to_last);
                $address = mb_substr($affiliation, $next_to_last);
                $address = ltrim($address, ", ");
            }

            $organizations[] = $organization;

            if (!in_array($address, $addresses))
            {
                $addresses[] = $address;
            }
        }

        $affiliation = new Affiliation();

        foreach ($organizations as $organization)
        {
            $affiliation->Organization .= '; ' .  $organization;
        }
        $affiliation->Organization = ltrim($affiliation->Organization, '; ');

        foreach ($addresses as $address)
        {
            $affiliation->Address .= '; ' . $address;
        }
        $affiliation->Address = ltrim($affiliation->Address, '; ');

        return $affiliation;
    }

    /**
     * @param $givenName string
     * @return string Initials with dots and whitespace between
     */
    protected function _createInitials($givenName)
    {
        $names = explode(' ', $givenName);
        $initialsArray = array();

        foreach ($names as $name)
        {
            $initial = mb_substr($name, 0, 1);
            if (mb_strtoupper($initial) === $initial)
            {
                $initialsArray[] = mb_strtoupper($initial);
            }
        }

        $initials = '';
        foreach ($initialsArray as $initial)
        {
            $initials = $initials . $initial . '. ';
        }
        $initials = rtrim($initials, " ");

        return $initials;
    }
    /**
     * @param $issue Issue
     * @return string Starting and ending pages string (start-end).
     */
    protected function _getIssuePages($issue)
    {
        $publishedArticleDao = DAORegistry::getDAO('PublishedArticleDAO');
        $submissionDao = Application::getSubmissionDAO();

        $publishedArticles = $publishedArticleDao->getPublishedArticles($issue->getId());

        $startingPages = array();
        $endingPages = array();

        foreach ($publishedArticles as $publishedArticle)
        {
            $submission = $submissionDao->getById($publishedArticle->getId());
            $startingPages[] = intval($submission->getStartingPage());
            $endingPages[] = intval($submission->getEndingPage());
        }

        $issueStartingPage = strval(min($startingPages));
        $issueEndingPage = strval(max($endingPages));

        return $issueStartingPage . '-' . $issueEndingPage;
    }

    /**
     * @param $locale string
     * @return string
     */
    protected function _convertLocaleToISO639($locale)
    {
        $lang2chars = substr($locale, 0, 2);
        $iso = $this->_getISO639();
        $language = $iso->languageByCode1($lang2chars);
        $lang3chars = $iso->code3ByLanguage($language);

        if ($lang3chars == '')
        {
            $user = Application::getRequest()->getUser();
            $notificationManager = new NotificationManager();
            $notificationManager->createTrivialNotification($user->getId(),
                NOTIFICATION_TYPE_WARNING,
                array('pluginName' => $this->getDisplayName(),
                    'contents' => "Error in converting UNIX locale (" . $locale . ") to ISO639 language! Empty language string will be used."));
        }

        return strtoupper($lang3chars);
    }

    /**
     * @param $lang3chars string
     * @return string
     */
    protected function _convertISO639ToLocale($lang3chars)
    {
        $lang3chars = strtolower($lang3chars);
        $iso = $this->_getISO639();
        $language = $iso->languageByCode3($lang3chars);
        $lang2chars = $iso->code1ByLanguage($language);
        $locales = AppLocale::getSupportedLocales();

        foreach ($locales as $locale=>$_)
        {
            $lang2charsFromLocale = substr($locale, 0, 2);
            if (str_contains($lang2charsFromLocale, $lang2chars))
            {
                return $locale;
            }
        }

        // If can't convert ISO639 to UNIX locale:
        $user = Application::getRequest()->getUser();
        $notificationManager = new NotificationManager();
        $notificationManager->createTrivialNotification($user->getId(),
                                            NOTIFICATION_TYPE_WARNING,
                                                        array('pluginName' => $this->getDisplayName(),
                                                              'contents' => "Error in converting ISO639 language (" . $lang3chars . ") to UNIX locale! Primary locale will be used."));
        return AppLocale::getPrimaryLocale();
    }

    /**
     * @var ISO639
     */
    protected $_iso;

    /**
     * @return ISO639
     */
    protected function _getISO639()
    {
        if (!is_a($this->_iso, $this))
        {
            require_once($_SERVER['DOCUMENT_ROOT'] . '/plugins/importexport/rsciexport/php-iso-639/src/ISO639.php'); // TODO: import without $_SERVER.
            $this->_iso = new ISO639();
        }
        return $this->_iso;
    }

    protected $_exportSettings;

    /**
     * @param $exportSettings array
     */
    public function SetExportSettings($exportSettings)
    {
        $this->_exportSettings = $exportSettings;
    }


    /**
     * @param DOMDocument $doc
     * @param $article Article
     * @param string[] $langs
     * @return DOMElement|false
     */
    protected function _createSectionNode($doc, $article, $langs)
    {
        $sectionDAO = DAORegistry::getDAO('SectionDAO');
        $section = $sectionDAO->getById($article->getSectionId());
        $sectionNode = $doc->createElement('section');

        foreach ($langs as $lang)
        {
            $locale = $this->_convertISO639ToLocale($lang);
            $secTitleNode = $doc->createElement('secTitle', $section->getTitle($locale));
            $secTitleNode->setAttribute('lang', $lang);
            $sectionNode->appendChild($secTitleNode);
        }
        return $sectionNode;
    }

    /**
     * @param $articles Article[]
     * @return Article[]
     */
    protected function _sortArticlesByPages($articles)
    {
        $startingPages = array();

        foreach ($articles as $article)
        {
            $startingPage = intval($article->getStartingPage());
            $startingPages += array($startingPage => $article);
        }

        ksort($startingPages, SORT_NUMERIC);

        return array_values($startingPages);
    }
}

class Affiliation
{
    public $Organization = '';
    public $Address = '';
}


