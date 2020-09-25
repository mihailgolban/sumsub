<?php


namespace Sumsub;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\MultipartStream;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use function GuzzleHttp\Psr7\stream_for;

define('SUMSUB_SECRET_KEY', getenv('sumsub.secretKey'));
define('SUMSUB_APP_TOKEN', getenv('sumsub.token'));
define('SUMSUB_BASE_URL', getenv('sumsub.baseUrl'));

class Api
{
    /**
     * https://developers.sumsub.com/api-reference/#creating-an-applicant
     * @param string $externalUserId
     * @param array $requiredIdDocs
     * @param string $lang
     * @return string
     * @throws GuzzleException
     */
    public function createApplicant(string $externalUserId, array $requiredIdDocs, string $lang = 'en'): string
    {
        $requestBody = [
            'externalUserId' => $externalUserId,
            'requiredIdDocs' => $requiredIdDocs,
            'lang' => $lang
        ];
        $url = '/resources/applicants';
        $request = new \GuzzleHttp\Psr7\Request('POST', SUMSUB_BASE_URL.$url);
        $request = $request->withHeader('Content-Type', 'application/json');
        $request = $request->withBody(stream_for(json_encode($requestBody)));

        $responseBody = $this->sendHttpRequest($request, $url)->getBody();
        return json_decode($responseBody, true)['id'];
    }

    /**
     * @param RequestInterface $request
     * @param $url
     * @return ResponseInterface
     * @throws GuzzleException
     */
    public function sendHttpRequest(RequestInterface $request, $url): ResponseInterface
    {
        $client = new Client();
        $ts = round(time());

        $request = $request->withHeader('X-App-Token', SUMSUB_APP_TOKEN);
        $request = $request->withHeader('X-App-Access-Sig', $this->createSignature($ts, $request->getMethod(), $url, $request->getBody()));
        $request = $request->withHeader('X-App-Access-Ts', $ts);


        return $client->send($request);
    }

    private function createSignature($ts, $httpMethod, $url, $httpBody): string
    {
        return hash_hmac('sha256', $ts . strtoupper($httpMethod) . $url . $httpBody, SUMSUB_SECRET_KEY);
    }

    /**
     * @param string $file
     * @param string $applicantId
     * @return string
     * @throws GuzzleException
     */
    public function addDocument(string $file, string $applicantId): string
    {
        // https://developers.sumsub.com/api-reference/#adding-an-id-document
        $metadata = $metadata = ['idDocType' => 'PASSPORT', 'country' => 'GBR'];

        $multipart = new MultipartStream([
            [
                "name" => "metadata",
                "contents" => json_encode($metadata)
            ],
            [
                "name" => "content",
                "contents" => fopen($file, 'r')
            ]
        ]);

        $url = "/resources/applicants".$applicantId."/info/idDoc";
        $request = new Request('POST', SUMSUB_BASE_URL.$url);
        $request = $request->withBody($multipart);
        return $this->sendHttpRequest($request, $url)->getHeader('X-Image-Id')[0];
    }

    /**
     * @param $applicantId
     * @return array
     * @throws GuzzleException
     */
    public function getApplicantStatus($applicantId): array
    {
        // https://developers.sumsub.com/api-reference/#getting-applicant-status-api
        $url = "/resources/applicants/" . $applicantId . "/requiredIdDocsStatus";
        $request = new Request('GET', SUMSUB_BASE_URL.$url);

        $stream = $this->sendHttpRequest($request, $url)->getBody();
        return json_decode($stream, true);
    }

    /**
     * @param string $applicantId
     * @return StreamInterface
     * @throws GuzzleException
     */
    public function getApplicantStatusSDK(string $applicantId): StreamInterface
    {
        // https://developers.sumsub.com/api-reference/#getting-applicant-status-sdk
        $url = "/resources/applicants/" . $applicantId . "/status";
        $request = new Request('GET', SUMSUB_BASE_URL.$url);

        return $this->sendHttpRequest($request, $url)->getBody();
    }

    /**
     * @param string $userId
     * @return string
     * @throws GuzzleException
     */
    public function getAccessToken(string $userId): string
    {
        // https://developers.sumsub.com/api-reference/#access-tokens-for-sdks
        $url = "/resources/accessTokens?userId=" . $userId;
        $request = new Request('POST', SUMSUB_BASE_URL.$url);
        $response = $this->sendHttpRequest($request, $url)->getBody();
        return json_decode($response, true)['token'];
    }

    /**
     * @param string $applicantId
     * @return array
     * @throws GuzzleException
     */
    public function getApplicantDataByApplicantId(string $applicantId): array
    {
        //https://developers.sumsub.com/api-reference/#getting-applicant-data
        $url = "/resources/applicants/" . $applicantId . "/one";
        $request = new Request('GET', SUMSUB_BASE_URL.$url);
        $response = $this->sendHttpRequest($request, $url)->getBody();
        return json_decode($response, true);
    }

    /**
     * @param string $userId
     * @return array
     * @throws GuzzleException
     */
    public function getApplicantDataByUserId(string $userId): array
    {
        //https://developers.sumsub.com/api-reference/#getting-applicant-data
        $url = "/resources/applicants/-;externalUserId=" . $userId . "/one";
        $request = new Request('GET', SUMSUB_BASE_URL.$url);
        $response = $this->sendHttpRequest($request, $url)->getBody();
        return json_decode($response, true);
    }

    /**
     * @param string $inspectionId
     * @param string $imageId
     * @return array
     * @throws GuzzleException
     */
    public function getDocumentImage(string $inspectionId, string $imageId): array
    {
        //https://developers.sumsub.com/api-reference/#getting-document-images
        $url = "/resources/inspections/$inspectionId/resources/$imageId";
        $request = new Request('GET', SUMSUB_BASE_URL.$url);
        $response = $this->sendHttpRequest($request, $url);
        return [
            'content' => $response->getBody(),
            'mime-type' => $response->getHeader('Content-Type')
        ];
    }

    /**
     * @param string $applicantId
     * @param string $inspectionId
     * @return array
     * @throws GuzzleException
     */
    public function getApplicantDocImages(string $applicantId, string $inspectionId): array
    {
        $images = [];
        $requiredIdDocsStatus = $this->getApplicantStatus($applicantId);
        foreach ($requiredIdDocsStatus as $doc) {
            foreach ($doc['imageIds'] as $imageId) {
                $images[$imageId] = [
                    'idDocType' => $doc['idDocType'],
                    'image' => $this->getDocumentImage($inspectionId, $imageId)
                ];
            }
        }
        return $images;
    }
}
