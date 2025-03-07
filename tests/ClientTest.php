<?php

namespace Utopia\Fetch;

use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    /**
     * End to end test for Client::fetch
     * Uses the PHP inbuilt server to test the Client::fetch method
     * @runInSeparateProcess
     * @dataProvider dataSet
     * @param string $url
     * @param string $method
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     * @param array<string, mixed> $query
     * @return void
     */
    public function testFetch(
        $url,
        $method,
        $body = [],
        $headers = [],
        $query = []
    ): void {
        $resp = null;
        try {
            $resp = Client::fetch(
                url: $url,
                method: $method,
                headers: $headers,
                body: $body,
                query: $query
            );
        } catch (FetchException $e) {
            echo $e;
            return;
        }
        if ($resp->getStatusCode()===200) { // If the response is OK
            $respData = $resp->json(); // Convert body to array
            $this->assertEquals($respData['method'], $method); // Assert that the method is equal to the response's method
            if($method != Client::METHOD_GET) {
                if(empty($body)) { // if body is empty then response body should be an empty string
                    $this->assertEquals($respData['body'], '');
                } else {
                    if($headers['content-type']!="application/x-www-form-urlencoded") {
                        $this->assertEquals( // Assert that the body is equal to the response's body
                            $respData['body'],
                            json_encode($body) // Converting the body to JSON string
                        );
                    }
                }
            }
            $this->assertEquals($respData['url'], $url); // Assert that the url is equal to the response's url
            $this->assertEquals(
                json_encode($respData['query']), // Converting the query to JSON string
                json_encode($query) // Converting the query to JSON string
            ); // Assert that the args are equal to the response's args
            $respHeaders = json_decode($respData['headers'], true); // Converting the headers to array
            $host = $respHeaders['Host'];
            if(array_key_exists('Content-Type', $respHeaders)) {
                $contentType = $respHeaders['Content-Type'];
            } else {
                $contentType = $respHeaders['content-type'];
            }
            $contentType = explode(';', $contentType)[0];
            $this->assertEquals($host, $url); // Assert that the host is equal to the response's host
            if(empty($headers)) {
                if(empty($body)) {
                    $this->assertEquals($contentType, 'application/x-www-form-urlencoded');
                } else {
                    $this->assertEquals($contentType, 'application/json');
                }
            } else {
                $this->assertEquals($contentType, $headers['content-type']); // Assert that the content-type is equal to the response's content-type
            }
        } else { // If the response is not OK
            echo "Please configure your PHP inbuilt SERVER";
        }
    }
    /**
     * Test for sending a file in the request body
     * @dataProvider sendFileDataSet
     * @return void
     */
    public function testSendFile(
        string $path,
        string $contentType,
        string $fileName
    ): void {
        $resp = null;
        try {
            $resp = Client::fetch(
                url: 'localhost:8000',
                method: Client::METHOD_POST,
                headers: [
                    'content-type' => 'multipart/form-data'
                ],
                body: [
                    'file' => new \CURLFile(strval(realpath($path)), $contentType, $fileName)
                ],
                query: []
            );
        } catch (FetchException $e) {
            echo $e;
            return;
        }
        if ($resp->getStatusCode()===200) { // If the response is OK
            $respData = $resp->json(); // Convert body to array
            if(isset($respData['method'])) {
                $this->assertEquals($respData['method'], Client::METHOD_POST);
            } // Assert that the method is equal to the response's method
            $this->assertEquals($respData['url'], 'localhost:8000'); // Assert that the url is equal to the response's url
            $this->assertEquals(
                json_encode($respData['query']), // Converting the query to JSON string
                json_encode([]) // Converting the query to JSON string
            ); // Assert that the args are equal to the response's args
            $files = [ // Expected files array from response
                'file' => [
                    'name' => $fileName,
                    'full_path'=> $fileName,
                    'type'=> $contentType,
                    'error'=> 0
                ]
            ];
            $resp_files = json_decode($respData['files'], true);
            $this->assertEquals($files['file']['name'], $resp_files['file']['name']);
            $this->assertEquals($files['file']['full_path'], $resp_files['file']['full_path']);
            $this->assertEquals($files['file']['type'], $resp_files['file']['type']);
            $this->assertEquals($files['file']['error'], $resp_files['file']['error']);
        } else { // If the response is not OK
            echo "Please configure your PHP inbuilt SERVER";
        }
    }
    /**
     * Test for getting a file as a response
     * @dataProvider getFileDataSet
     * @return void
     */
    public function testGetFile(
        string $path,
        string $type
    ): void {
        $resp = null;
        try {
            $resp = Client::fetch(
                url: 'localhost:8000/'.$type,
                method: Client::METHOD_GET,
                headers: [],
                body: [],
                query: []
            );
        } catch (FetchException $e) {
            echo $e;
            return;
        }
        if ($resp->getStatusCode()===200) { // If the response is OK
            $data = fopen($path, 'rb');
            $size=filesize($path);
            if($data && $size) {
                $contents= fread($data, $size);
                fclose($data);
                $this->assertEquals($resp->getBody(), $contents); // Assert that the body is equal to the expected file contents
            } else {
                echo "Invalid file path in testcase";
            }
        } else { // If the response is not OK
            echo "Please configure your PHP inbuilt SERVER";
        }
    }
    /**
     * Test for redirect
     * @return void
     */
    public function testRedirect(): void
    {
        $resp = null;
        try {
            $resp = Client::fetch(
                url: 'localhost:8000/redirect',
                method: Client::METHOD_GET,
                headers: [],
                body: [],
                query: []
            );
        } catch (FetchException $e) {
            echo $e;
            return;
        }
        if ($resp->getStatusCode()===200) { // If the response is OK
            $respData = $resp->json(); // Convert body to array
            $this->assertEquals($respData['page'], "redirectedPage"); // Assert that the page is the redirected page
        } else { // If the response is not OK
            echo "Please configure your PHP inbuilt SERVER";
        }
    }
    /**
     * Data provider for testFetch
     * @return array<string, array<mixed>>
     */
    public function dataSet(): array
    {
        return [
            'get' => [
                'localhost:8000',
                Client::METHOD_GET
            ],
            'getWithQuery' => [
                'localhost:8000',
                Client::METHOD_GET,
                [],
                [],
                [
                    'name' => 'John Doe',
                    'age' => '30',
                ],
            ],
            'postNoBody' => [
                'localhost:8000',
                Client::METHOD_POST
            ],
            'postJsonBody' => [
                'localhost:8000',
                Client::METHOD_POST,
                [
                    'name' => 'John Doe',
                    'age' => 30,
                ],
                [
                    'content-type' => 'application/json'
                ],
            ],
            'postFormDataBody' => [
                'localhost:8000',
                Client::METHOD_POST,
                [
                    'name' => 'John Doe',
                    'age' => 30,
                ],
                [
                    'content-type' => 'application/x-www-form-urlencoded'
                ],
            ]
        ];
    }

    /**
     * Data provider for testSendFile
     * @return array<string, array<mixed>>
     */
    public function sendFileDataSet(): array
    {
        return [
            'imageFile' => [
                __DIR__.'/resources/logo.png',
                'image/png',
                'logo.png'
            ],
            'textFile' => [
                __DIR__.'/resources/test.txt',
                'text/plain',
                'text.txt'
            ],
        ];
    }
    /**
     * Data provider for testGetFile
     * @return array<string, array<mixed>>
     */
    public function getFileDataset(): array
    {
        return [
            'imageFile' => [
                __DIR__.'/resources/logo.png',
                'image'
            ],
            'textFile' => [
                __DIR__.'/resources/test.txt',
                'text'
            ],
        ];
    }
}
