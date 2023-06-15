<?php

use Psr\Http\Message\UriInterface;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\RequestException;
use Spatie\Crawler\Crawler;
use Spatie\Crawler\CrawlObservers\CrawlObserver;
use libphonenumber\PhoneNumberUtil;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberType;

require_once __DIR__ . '/vendor/autoload.php';

class ContactCrawler extends CrawlObserver
{
    private $contacts = [];
    private $phoneNumberUtil;
    private $phonePattern;

    public function __construct()
    {
        $this->phoneNumberUtil = PhoneNumberUtil::getInstance();
        $this->phonePattern = '/\b(\+\d{1,3}\s?)?(\(\d{1,3}\)\s?)?(\d{1,4}[-.\s]?){1,4}\b/';
    }

    public function crawled(UriInterface $url, ResponseInterface $response, ?UriInterface $foundOnUrl = null, ?string $linkText = null): void
    {
        // Extract contacts from the crawled page
        $pageContacts = $this->extractContacts($response);

        // Add the extracted contacts to the overall contacts list
        $this->contacts = array_merge($this->contacts, $pageContacts);
    }

    public function crawlFailed(UriInterface $url, RequestException $requestException, ?UriInterface $foundOnUrl = null, ?string $linkText = null): void
    {
        // if (empty($this->contacts)) {
        //     echo "No contacts found";
        //     return;
        // }
    
        // // Normalize and print the contacts found
        // foreach ($this->contacts as $contact) {
        //     echo $this->normalizePhoneNumber($contact) . "<br>";
        // }
    }

    public function finishedCrawling(): void
    {
        if (empty($this->contacts)) {
            echo "No contacts found";
            return;
        }
    
        // Normalize and print the contacts found
        foreach ($this->contacts as $contact) {
            echo $this->normalizePhoneNumber($contact) . "<br>";
        }
    }

    private function isPhoneNumberValid($phoneNumber)
    {
        try {
            $number = $this->phoneNumberUtil->parse($phoneNumber, null);
            return $this->phoneNumberUtil->isValidNumber($number);
        } catch (Exception $e) {
            //  catching error from log
            error_log($e->getMessage());
            return false;
        }
    }

    private function isCellPhoneNumber($phoneNumber)
    {
        // Parse the phone number
        $number = $this->phoneNumberUtil->parse($phoneNumber, null);

        // Check if the number is a valid cell phone number
        $numberType = $this->phoneNumberUtil->getNumberType($number);

        return $numberType === PhoneNumberType::MOBILE;
    }

    // extract contacts from html file
    private function extractContacts($response)
    {
        $html = $response->getCachedBody();
        $contacts = [];

        preg_match_all($this->phonePattern, $html, $matches);

        if (!empty($matches[0])) {
            foreach ($matches[0] as $phoneNumber) {
                if ($this->isPhoneNumberValid($phoneNumber) ) {
                    $contacts[] = $phoneNumber;
                }
            }
        }

        return $contacts;
    }

    private function normalizePhoneNumber($phoneNumber)
    {
        // Parse the phone number
        $number = $this->phoneNumberUtil->parse($phoneNumber, null);

        // Format the phone number in international format
        return $this->phoneNumberUtil->format($number, PhoneNumberFormat::INTERNATIONAL);
    }
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Specify the URL to crawl
$url = 'https://randommer.io/Phone';

// Set the maximum pages to crawl per website
$maxPagesPerWebsite = 10;

// Create an instance of the Crawler
$crawler = Crawler::create();

// Set the crawler's options
$crawler
    ->setTotalCrawlLimit($maxPagesPerWebsite)
    ->addCrawlObserver(new ContactCrawler())
    ->setCrawlProfile(new \Spatie\Crawler\CrawlProfiles\CrawlSubdomains($url))
    ->ignoreRobots();

// Start the crawling process
$crawler->startCrawling($url);
