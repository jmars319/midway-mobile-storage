<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../email_scheduler.php';

class EmailSchedulerTest extends TestCase {
    private $scheduler;

    protected function setUp(): void {
        // use in-memory sqlite for fast isolated tests
        $this->scheduler = new EmailScheduler(':memory:');
    }

    public function testCreateListDeleteCampaign() {
        $data = [
            'name' => 'Test Campaign',
            'subject' => 'Hello',
            'body' => 'Body text',
            'recipients' => ['a@example.com','b@example.com'],
            'send_days' => ['monday'],
            'send_time' => '09:00',
            'active' => 1
        ];

        $id = $this->scheduler->createCampaign($data);
        $this->assertIsNumeric($id);

        $campaigns = $this->scheduler->getCampaigns();
        $this->assertNotEmpty($campaigns);

        $found = null;
        foreach ($campaigns as $c) { if ($c['id'] == $id) { $found = $c; break; } }
        $this->assertNotNull($found);
        $this->assertEquals('Test Campaign', $found['name']);

        $ok = $this->scheduler->deleteCampaign($id);
        $this->assertTrue((bool)$ok);

        $campaigns2 = $this->scheduler->getCampaigns();
        $this->assertCount(0, $campaigns2);
    }

    public function testAddAndDeleteSupplier() {
        $data = [
            'name' => 'With Supplier',
            'subject' => 'S',
            'body' => 'B',
            'recipients' => ['x@x.com'],
            'send_days' => ['tuesday'],
            'send_time' => '10:00',
            'active' => 1
        ];
        $cid = $this->scheduler->createCampaign($data);
        $this->assertIsNumeric($cid);

        $sup = ['name'=>'Sup','url'=>'http://example.com','selectors'=>['price'=>'.price']];
        $ok = $this->scheduler->addSupplier($cid, $sup);
        $this->assertTrue((bool)$ok);

        $slist = $this->scheduler->getSuppliersByCampaign($cid);
        $this->assertCount(1, $slist);
        $this->assertEquals('Sup', $slist[0]['name']);

        $sid = $slist[0]['id'];
        $del = $this->scheduler->deleteSupplier($sid);
        $this->assertTrue((bool)$del);

        $slist2 = $this->scheduler->getSuppliersByCampaign($cid);
        $this->assertCount(0, $slist2);
    }

    public function testListCampaignsPagination() {
        // create 12 campaigns
        for ($i=1;$i<=12;$i++){
            $this->scheduler->createCampaign([
                'name'=>"C$i",
                'subject'=>"S$i",
                'body'=>'b',
                'recipients'=>['a@a.com'],
                'send_days'=>['monday'],
                'send_time'=>'08:00',
                'active'=>($i%2)
            ]);
        }

        $page1 = $this->scheduler->listCampaigns(1,5);
        $this->assertArrayHasKey('total', $page1);
        $this->assertEquals(12, $page1['total']);
        $this->assertCount(5, $page1['campaigns']);

        $page3 = $this->scheduler->listCampaigns(3,5);
        $this->assertCount(2, $page3['campaigns']);
    }
}
