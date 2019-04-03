<?php
/**
 * Created by PhpStorm.
 * User: Matt
 * Date: 20/04/2016
 * Time: 2:32 PM
 */

namespace Freshdesk;

use Freshdesk\Exceptions\AccessDeniedException;
use Freshdesk\Exceptions\ApiException;
use Freshdesk\Exceptions\AuthenticationException;
use Freshdesk\Exceptions\ConflictingStateException;
use Freshdesk\Exceptions\RateLimitExceededException;
use Freshdesk\Exceptions\UnsupportedContentTypeException;
use Freshdesk\Resources\Agent;
use Freshdesk\Resources\BusinessHour;
use Freshdesk\Resources\Category;
use Freshdesk\Resources\Comment;
use Freshdesk\Resources\Company;
use Freshdesk\Resources\Contact;
use Freshdesk\Resources\Conversation;
use Freshdesk\Resources\EmailConfig;
use Freshdesk\Resources\Forum;
use Freshdesk\Resources\Group;
use Freshdesk\Resources\Product;
use Freshdesk\Resources\SLAPolicy;
use Freshdesk\Resources\Solution;
use Freshdesk\Resources\Ticket;
use Freshdesk\Resources\TimeEntry;
use Freshdesk\Resources\Topic;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * Class for interacting with the Freshdesk Api
 *
 * This is the only class that should be instantiated directly. All API resources are available
 * via the relevant public properties
 *
 * @package Api
 * @author Matthew Clarkson <mpclarkson@gmail.com>
 */
class Api
{
    /**
     * Solution resource
     *
     * @api
     * @var Solution
     */
    public $solutions;

    /**
     * @internal
     * @var Client
     */
    protected $client;

    /**
     * @internal
     * @var string
     */
    private $baseUrl;

    /**
     * Constructs a new api instance
     *
     * @api
     * @param string $apiKey
     * @param string $domain
     * @throws Exceptions\InvalidConfigurationException
     */
    public function __construct($apiKey, $domain)
    {
        $this->validateConstructorArgs($apiKey, $domain);

        $this->baseUrl = sprintf('https://%s.freshdesk.com/api/v2', $domain);

        $this->client = new Client([
                'auth' => [$apiKey, 'X']
            ]
        );

        $this->setupResources();
    }


    /**
     * Internal method for handling requests
     *
     * @internal
     * @param $method
     * @param $endpoint
     * @param array|null $data
     * @param array|null $query
     * @return mixed|null
     * @throws ApiException
     * @throws ConflictingStateException
     * @throws RateLimitExceededException
     * @throws UnsupportedContentTypeException
     */
    public function request($method, $endpoint, array $data = null, $query = null, $id = null)
    {
        $options = ['json' => $data];

        if (isset($query)) {
            $options['query'] = $query;
        }

        $url = $this->baseUrl . $endpoint;

        return $this->performRequest($method, $url, $options, $id);
    }

    /**
     * Performs the request
     *
     * @internal
     *
     * @param $method
     * @param $url
     * @param $options
     * @return mixed|null
     * @throws AccessDeniedException
     * @throws ApiException
     * @throws AuthenticationException
     * @throws ConflictingStateException
     */
    private function performRequest($method, $url, $options, $id = null) {
        try {
            switch ($method) {
                case 'GET':

                    return $this->checkForPages($url,$options,$id);
                case 'POST':
                    return json_decode($this->client->post($url, $options)->getBody(), true);
                case 'PUT':
                    return json_decode($this->client->put($url, $options)->getBody(), true);
                case 'DELETE':
                    return json_decode($this->client->delete($url, $options)->getBody(), true);
                default:
                    return null;
            }
        } catch (RequestException $e) {
            throw ApiException::create($e);
        }
    }

    private function checkForPages($url,$options,$id = null){
        if($this->client->get($url,$options)->hasHeader('link'))
        {
            $array = json_decode($this->
            client->
            get($url, $options)->
            getBody(), true);

            $newUrl = ($this->client->get($url, $options)->getHeader('link'));
            $newUrl = preg_replace('/[<>; ]/s', '', $newUrl);
            $newUrl = str_replace('rel="next"','',$newUrl);

            $mergedArray = array_merge(
                $array ,
                $this->checkForPages($newUrl[0],$options,$id));
        }
        else {
            $mergedArray = json_decode($this
                ->client
                ->get($url, $options)
                ->getBody(), true);

            if ($id)
            {
                $newArray = array();
                foreach ($mergedArray as $item) {
                    $item['category_id'] = $id;
                    array_push($newArray,$item);
                }
                $mergedArray = $newArray;
            }
        }
        return $mergedArray;
    }


    /**
     * @param $apiKey
     * @param $domain
     * @throws Exceptions\InvalidConfigurationException
     * @internal
     *
     */
    private function validateConstructorArgs($apiKey, $domain)
    {
        if (!isset($apiKey)) {
            throw new Exceptions\InvalidConfigurationException("API key is empty.");
        }

        if (!isset($domain)) {
            throw new Exceptions\InvalidConfigurationException("Domain is empty.");
        }
    }

    /**
     * @internal
     */
    private function setupResources()
    {
        //Solutions
        $this->solutions = new Solution($this);
    }
}
