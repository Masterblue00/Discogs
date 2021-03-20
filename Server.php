<?php

namespace SocialiteProviders\Discogs;

use GuzzleHttp\Exception\BadResponseException;
use League\OAuth1\Client\Credentials\TokenCredentials;
use SocialiteProviders\Manager\OAuth1\Server as BaseServer;
use SocialiteProviders\Manager\OAuth1\User;

class Server extends BaseServer
{
    /**
     * {@inheritdoc}
     */
    public function urlTemporaryCredentials()
    {
        return 'https://api.discogs.com/oauth/request_token';
    }

    /**
     * {@inheritdoc}
     */
    public function urlAuthorization()
    {
        return 'https://discogs.com/oauth/authorize';
    }

    /**
     * {@inheritdoc}
     */
    public function urlTokenCredentials()
    {
        return 'https://api.discogs.com/oauth/access_token';
    }

    /**
     * {@inheritdoc}
     */
    public function urlUserDetails()
    {
        return 'https://api.discogs.com/oauth/identity';
    }

    /**
     * {@inheritdoc}
     */
    public function urlProfileDetails(string $username)
    {
        return 'https://api.discogs.com/users/'.$username;
    }
    /**
     * {@inheritdoc}
     */
    public function userDetails($data, TokenCredentials $tokenCredentials)
    {
        $user = new User();
        $user->id = $data['id'];
        $user->nickname = $data['username'];
        $user->extra = array_diff_key($data, array_flip(['id', 'username']));

        return $user;
    }

    /**
     * {@inheritdoc}
     */
    public function userUid($data, TokenCredentials $tokenCredentials)
    {
        return $data['id'];
    }

    /**
     * {@inheritdoc}
     */
    public function userEmail($data, TokenCredentials $tokenCredentials)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function userScreenName($data, TokenCredentials $tokenCredentials)
    {
        return $data['username'];
    }

    /**
     * Fetch user details from the remote service.
     *
     * @param TokenCredentials $tokenCredentials
     * @param bool             $force
     *
     * @return array HTTP client response
     */
    protected function fetchUserDetails(TokenCredentials $tokenCredentials, $force = true)
    {
        if ( ! $this->cachedUserDetailsResponse || $force) {
            $url = $this->urlUserDetails();

            $client = $this->createHttpClient();

            $headers = $this->getHeaders($tokenCredentials, 'GET', $url);
            $userDetails = $this->queryApi($client, $headers, $this->urlUserDetails());

            $response = $this->parseResponse($userDetails);
            $profileDetails = $this->queryApi($client, $headers, $this->urlProfileDetails($response['username']));

            $this->cachedUserDetailsResponse = $this->parseResponse($profileDetails);
        }

        return $this->cachedUserDetailsResponse;
    }

    protected function queryApi($client, $headers, string $url) {
        try {
            $response = $client->get($url, [
                'headers' => $headers,
            ]);
            return $response;
        } catch (BadResponseException $e) {
            $response = $e->getResponse();
            $body = $response->getBody();
            $statusCode = $response->getStatusCode();

            throw new \Exception(
                "Received error [$body] with status code [$statusCode] when retrieving token credentials."
            );
        }
    }

    protected function parseResponse($response)
    {
        switch ($this->responseType) {
            case 'json':
                return json_decode((string) $response->getBody(), true);
                break;

            case 'xml':
                return simplexml_load_string((string) $response->getBody());
                break;

            case 'string':
                $return = '';
                parse_str((string) $response->getBody(), $return);
                return $return;
                break;

            default:
                throw new \InvalidArgumentException("Invalid response type [{$this->responseType}].");
        }
    }
}
