<?php

namespace Tests\Ploi\Resources;

use Ploi\Exceptions\Resource\RequiresId;
use stdClass;
use Tests\BaseTest;
use Ploi\Http\Response;
use Ploi\Resources\Server;
use Ploi\Exceptions\Http\NotFound;
use Ploi\Exceptions\Http\NotValid;

/**
 * Class SiteTest
 *
 * @package Tests\Ploi\Resources
 */
class SiteTest extends BaseTest
{
    /**
     * @var Server
     */
    private $server;

    public function setup(): void
    {
        parent::setup(); // TODO: Change the autogenerated stub

        $resource = $this->getPloi()->server();
        $allServers = $resource->get();
        if (!empty($allServers->getJson()->data)) {
            $this->server = $resource->setId($allServers->getJson()->data[0]->id);
        }
    }

    public function testGetAllSites()
    {
        $resource = $this->server->sites();

        $sites = $resource->get();

        $this->assertInstanceOf(Response::class, $sites);
        $this->assertIsArray($sites->getJson()->data);
    }

    public function testGetPaginatedSites()
    {
        $resource = $this->server->sites();

        $sitesPage1 = $resource->perPage(5)->page();
        $sitesPage2 = $resource->page(2, 5);

        $this->assertInstanceOf(Response::class, $sitesPage1);
        $this->assertInstanceOf(Response::class, $sitesPage2);

        $this->assertIsArray($sitesPage1->getJson()->data);
        $this->assertIsArray($sitesPage2->getJson()->data);

        $this->assertEquals(1, $sitesPage1->getJson()->meta->current_page);
        $this->assertEquals(2, $sitesPage2->getJson()->meta->current_page);

        $this->assertEquals(5, $sitesPage1->getJson()->meta->per_page);
        $this->assertEquals(5, $sitesPage2->getJson()->meta->per_page);
    }

    /**
     * @throws \Ploi\Exceptions\Http\InternalServerError
     * @throws \Ploi\Exceptions\Http\NotFound
     * @throws \Ploi\Exceptions\Http\NotValid
     * @throws \Ploi\Exceptions\Http\PerformingMaintenance
     * @throws \Ploi\Exceptions\Http\TooManyAttempts
     */
    public function testGetSingleSite()
    {
        $resource = $this->server->sites();
        $sites = $resource->get();

        if (!empty($sites->getJson()->data[0])) {
            $siteId = $sites->getJson()->data[0]->id;

            $resource->setId($siteId);
            $methodOne = $resource->get();
            $methodTwo = $this->server->sites($siteId)->get();
            $methodThree = $this->server->sites()->get($siteId);

            $this->assertInstanceOf(stdClass::class, $methodOne->getJson()->data);
            $this->assertEquals($siteId, $methodOne->getJson()->data->id);
            $this->assertEquals($siteId, $methodTwo->getJson()->data->id);
            $this->assertEquals($siteId, $methodThree->getJson()->data->id);
        }
    }

    public function testCreateExampleDotCom(): stdClass
    {
        try {
            $response = $this->server->sites()->create('example.com');

            $this->assertInstanceOf(Response::class, $response);
            $this->assertNotEmpty($response->getData()->id);
            return $response->getData();
        } catch (\Exception $e) {
            $this->assertInstanceOf(NotValid::class, $e);

            $allSites = $this->server->sites()->get();
            $foundSite = false;
            foreach ($allSites->getJson()->data as $site) {
                if ($foundSite) {
                    break;
                }

                if ($site->domain === 'example.com') {
                    $this->server->sites($site->id)->delete();

                    $this->testCreateExampleDotCom();
                }
            }
        }
    }

    /**
     * @depends testCreateExampleDotCom
     */
    public function testDeleteSite($site)
    {
        if (!empty($site)) {
            $deleted = $this->server->sites($site->id)->delete();
            $this->assertTrue($deleted->getResponse()->getStatusCode() === 200);
        }
    }

    public function testDeleteInvalidSite()
    {
        try {
            $this->server->sites(1)->delete();
        } catch (\Exception $e) {
            $this->assertInstanceOf(NotFound::class, $e);
        }

        try {
            $this->server->sites()->delete(1);
        } catch (\Exception $e) {
            $this->assertInstanceOf(NotFound::class, $e);
        }
    }

    public function testLogs()
    {
        $resource = $this->server->sites();
        $sites = $resource->get();

        if (!empty($sites->getJson()->data[0])) {
            $siteId = $sites->getJson()->data[0]->id;

            $response = $resource->logs($siteId);
            $this->assertInstanceOf(Response::class, $response);

            $logs = $response->getData();
            $this->assertIsArray($logs);


            if (!empty($logs[0])) {
                $this->assertInstanceOf(stdClass::class, $logs[0]);
            }
        }
    }

    public function testSuspendSite()
    {
        $sites = $this->server->sites()->get();

        if (!empty($sites->getJson()->data[0])) {
            $siteId = $sites->getJson()->data[0]->id;
            $response = $this->server->sites($siteId)->suspend(null, 'Testing SDK');

            $this->assertTrue($response->getResponse()->getStatusCode() === 200);
        }

        try {
            $this->server->sites()->resume();
        } catch (\Exception $e) {
            $this->assertInstanceOf(RequiresId::class, $e);
        }
    }

    public function testResumeSite()
    {
        $sites = $this->server->sites()->get();

        if (!empty($sites->getJson()->data[0])) {
            $siteId = $sites->getJson()->data[0]->id;
            $response = $this->server->sites($siteId)->resume();

            $this->assertTrue($response->getResponse()->getStatusCode() === 200);
        }

        try {
            $this->server->sites()->resume();
        } catch (\Exception $e) {
            $this->assertInstanceOf(RequiresId::class, $e);
        }
    }
}
