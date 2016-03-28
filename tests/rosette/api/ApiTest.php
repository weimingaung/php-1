<?php

/**
 * @copyright 2014-2015 Basis Technology Corporation.
 *
 * Licensed under the Apache License, Version 2.0 (the "License"); you may not use this file except in compliance
 * with the License. You may obtain a copy of the License at
 * @license http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software distributed under the License is
 * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and limitations under the License.
 **/
namespace rosette\api;

use Symfony\Component\Config\Definition\Exception\Exception;

/**
 * Mock the global function for this test.
 *
 * @param $filename
 * @param null $flags
 * @param null $context
 * @param null $offset
 * @param null $maxlen
 *
 * @return string mocked response
 */

function curl_exec($ch)
{
    $data = array ('response'=>'{"content": "Mocked response content"}');
   return '{"content": "Mocked response content"}';
}

function curl_getinfo($ch)
{
    return 200;
}

// It is better to use phpunit --bootstrap ./vendor/autoload.php than to play with
// the pathing.
require_once __DIR__ . '/../../../vendor/autoload.php';

/**
 * Class ApiTest.
 */
class ApiTest extends \PHPUnit_Framework_TestCase
{
    private $userKey = null;
    private static $mockDir = '/../../../mock-data';
    public static $requestDir;
    public static $responseDir;

    /**
     * setup mock data paths.
     */
    public static function setupBeforeClass()
    {
        self::$requestDir = __DIR__ . self::$mockDir . '/request/';
        self::$responseDir = __DIR__ . self::$mockDir . '/response/';
    }

    /**
     * Find the correct response file from the mock-data directory
     * Used to replace the retryingRequest function for mocking.
     *
     * @param $filename
     *
     * @return mixed|string
     */
    private function getMockedResponse($filename)
    {
        //$response = json_decode(\file_get_contents(self::$responseDir . $filename . '.json'), true);

        //return $response;
        return curl_exec($ch = null);
    }

    /**
     * Replace the getResponseCode method in the API class for mocking purposes.
     *
     * @param $filename
     *
     * @return int
     */
    private function getMockedResponseCode($filename)
    {
        //return intval(\file_get_contents(self::$responseDir . $filename . '.status'));
        return curl_getinfo($ch = null);
    }

    /**
     * Mock the api so that getResponseCode can return the code from the test file.
     *
     * @param $userKey
     *
     * @return mixed
     */
    private function setUpApi($userKey)
    {
        $api = $this->getMockBuilder('rosette\api\Api')
                    ->setConstructorArgs(array($userKey))
                    ->setMethods(array('getResponseStatusCode'))
                    ->getMock();
        $api->method('getResponseStatusCode')
            ->willReturn($this->getMockedResponseCode($userKey));
        return $api;
    }

    /**
     * @group posts
     * @expectedException \rosette\api\RosetteException
     */
    public function testCheckVersion()
    {
        $this->userKey = 'checkVersion';
        $api = $this->setUpApi($this->userKey);
        $api->checkVersion('http://rosette.basistech.com');
    }

    /**
     * @group gets
     */
    public function testInfo()
    {
        $expected = $this->getMockedResponse('info');
        $this->userKey = 'info';
        $api = $this->setUpApi($this->userKey);
        $result = $api->info();
        $this->assertSame($expected, $result[0]);
    }

    /**
     * @group gets
     */
    public function testPing()
    {
        $expected = $this->getMockedResponse('ping');
        $this->userKey = 'ping';
        $api = $this->setUpApi($this->userKey);
        $result = $api->ping();
        $this->assertSame($expected, $result[0]);
    }

    /**
     * Get the file body for a request given a partial file name.
     *
     * @param $filename
     *
     * @return mixed
     */
    private function getRequestData($filename)
    {
        $request = \file_get_contents(self::$requestDir . $filename . '.json');

        return json_decode($request, true);
    }

    /**
     * Return an array of arrays to be passed to testLanguages
     * Each sub array is of the form [file name (after request/ and before .json), endpoint].
     *
     * @return array
     */
    public function findFiles()
    {
        // can't use global $requestDir due to a dataProvider issue where it's called before
        // everything else, include static functions:
        //    https://github.com/sebastianbergmann/phpunit/issues/1206
        // so workaround until that improvement is implemented
        $requestDir = __DIR__ . self::$mockDir . '/request/';

        $pattern = '/.*\/request\/([\w\d]*-[\w\d]*-(.*))\.json/';
        $files = array();
        foreach (glob($requestDir . '*.json') as $filename) {
            preg_match($pattern, $filename, $output_array);
            $files[] = array($output_array[1], $output_array[2]);
        }

        return $files;
    }

    /**
     * Test all endpoints (other than ping and info).
     *
     * @group posts
     * @dataProvider findFiles
     *
     * @param $filename
     * @param $endpoint
     */
    public function testEndpoints($filename, $endpoint)
    {
        // Set user key as file name because a real user key is unnecessary for testing
        $this->userKey = $filename;  // ex 'eng-sentence-language';
        $api = $this->setUpApi($this->userKey);
        $api->skipVersionCheck();  // need to set it so it doesn't call the mocked info()
        $api->setDebug(true);
        $input = $this->getRequestData($this->userKey);
        $expected = $this->getMockedResponse($this->userKey);
        if ($endpoint === 'name-similarity') {
            $sourceName = new Name(
                $input['name1']['text'],
                $input['name1']['entityType'],
                $input['name1']['language'],
                $input['name1']['script']
            );
            $targetName = new Name(
                $input['name2']['text'],
                $input['name2']['entityType'],
                $input['name2']['language'],
                $input['name2']['script']
            );
            $params = new NameSimilarityParameters($sourceName, $targetName);
        } else {
            if ($endpoint === 'name-translation') {
                $params = new NameTranslationParameters();
            } elseif ($endpoint === 'relationships') {
                $params = new RelationshipsParameters();
            } else {
                $params = new DocumentParameters();
            }
            // Fill in parameters object with data if it is not name-similarity (because those parameters are formatted
            // differently and handled when the object is created.
            foreach (array_keys($input) as $key) {
                $params->set($key, $input[$key]);
            }
        }

        // Find the correct call to make and call it.
        // If it does not throw an exception, check that it was not supposed to and if so check that it
        // returns the correct thing.
        // If it throws an exception, check that it was supposed to and if so pass otherwise fail test.
        //try {
            $result = '';
            if ($endpoint === 'categories') {
                $result = $api->categories($params);
            }
            if ($endpoint === 'entities') {
                $result = $api->entities($params);
            }
            if ($endpoint === 'entities_linked') {
                $result = $api->entities($params, true);
            }
            if ($endpoint === 'language') {
                $result = $api->language($params);
            }
            if ($endpoint === 'name-similarity') {
                $result = $api->nameSimilarity($params);
            }
            if ($endpoint === 'morphology_complete') {
                $result = $api->morphology($params);
            }
            if ($endpoint === 'sentiment') {
                $result = $api->sentiment($params);
            }
            if ($endpoint === 'name-translation') {
                $result = $api->nameTranslation($params);
            }
            if ($endpoint === 'relationships') {
                $result = $api->relationships($params);
            }
            // If there is a "code" key, it means an exception should be thrown
            //if (!array_key_exists('code', $expected)) {
                $this->assertEquals($expected, $result[0]);

            //}
        //} catch (RosetteException $exception) {
           // $this->assertSame('unsupportedLanguage', $expected['code']);
        //}
    }

}