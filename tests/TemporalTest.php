<?php

namespace Tarantool\Temporal\Tests;

use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use Tarantool\Client\Client;
use Tarantool\Mapper\Mapper;
use Tarantool\Temporal\Entity\Reference;
use Tarantool\Temporal\Temporal;

class TemporalTest extends TestCase
{
    public function createTemporal(): Temporal
    {
        $host = getenv('TARANTOOL_HOST');
        $port = getenv('TARANTOOL_PORT') ?: 3301;
        $client = Client::fromDsn("tcp://$host:$port");
        $mapper = new Mapper($client);
        $mapper->dropUserSpaces();
        return new Temporal($mapper);
    }


    public function testReferenceCacheClear()
    {
        $temporal = $this->createTemporal();
        $temporal->setActor(1);
        $this->assertSame($temporal->getActor(), 1);

        $temporal->reference([
            'person' => 1,
            'data' => [
                'position' => 1
            ]
        ]);

        $this->assertSame($temporal->getReference('person', 1, 'position', 'now'), 1);

        $temporal->reference([
            'begin' => Carbon::now()->format('Ymd'),
            'person' => 1,
            'data' => [
                'position' => 2
            ]
        ]);

        $this->assertSame($temporal->getReference('person', 1, 'position', 'now'), 2);
    }

    public function testReferenceSchema()
    {
        $temporal = $this->createTemporal();
        $this->assertSame(null, $temporal->getReference('person', 1, 'position', 'now'));
    }

    public function testReferencesSchema()
    {
        $temporal = $this->createTemporal();
        $this->assertSame([], $temporal->getReferences('person', 1, 'position', 'now'));
    }

    public function testStateSchema()
    {
        $temporal = $this->createTemporal();
        $this->assertSame([], $temporal->getState('person', 1, 'now'));
    }

    public function testOverrideSchema()
    {
        $temporal = $this->createTemporal();
        $this->assertSame([], $temporal->getOverrides('person', 1));
    }

    public function testLinkSchema()
    {
        $temporal = $this->createTemporal();
        $this->assertSame([], $temporal->getLinks('person', 1, 'now'));
    }

    public function testTemporalReference()
    {
        $temporal = $this->createTemporal();
        $temporal->setActor(1);

        $temporal->reference([
            'person'  => 11,
            'begin' => 20170801,
            'data'  => [
                'position' => 1,
            ]
        ]);

        $this->assertCount(1, $temporal->find('_temporal_reference'));
        $states = $temporal->find('_temporal_reference_state');
        $this->assertCount(1, $states);
        $this->assertSame($states[0]->id, 11);

        $this->assertNull($temporal->getReference('person', 11, 'position', 20170705));
        $this->assertEquals(1, $temporal->getReference('person', 11, 'position', 20170810));

        $this->assertCount(1, $temporal->getReferenceLog('person', 11, 'position'));

        $temporal->reference([
            'person'  => 11,
            'begin' => 20170815,
            'data'  => [
                'position' => 2,
            ]
        ]);

        $states = $temporal->getReferenceStates('person', 11, 'position', 20170815, 20181123);
        $this->assertCount(1, $states);
        $this->assertSame($states[0]['begin'], 20170815);
        $this->assertSame($states[0]['end'], 20181123);

        $states = $temporal->getReferenceStates('person', 11, 'position', 20170819, 20181123);
        $this->assertCount(1, $states);
        $this->assertSame($states[0]['begin'], 20170819);
        $this->assertSame($states[0]['end'], 20181123);

        $states = $temporal->getReferenceStates('person', 11, 'position', 20170810, 20181123);
        $this->assertCount(2, $states);
        $this->assertSame($states[0]['begin'], 20170810);
        $this->assertSame($states[0]['end'], 20170815);
        $this->assertSame($states[1]['begin'], 20170815);
        $this->assertSame($states[1]['end'], 20181123);


        $states = $temporal->getReferenceStates('person', 11, 'position', 20170715, 20181123);
        $this->assertCount(2, $states);
        $this->assertSame($states[0]['begin'], 20170801);
        $this->assertSame($states[0]['end'], 20170815);
        $this->assertSame($states[1]['begin'], 20170815);
        $this->assertSame($states[1]['end'], 20181123);


        $temporal->reference([
            'person'  => 22,
            'begin' => 20170825,
            'data'  => [
                'position' => 2,
            ]
        ]);

        $temporal->reference([
            'person'  => 22,
            'begin' => 20170810,
            'data'  => [
                'position' => 1,
            ]
        ]);

        $this->assertCount(2, $temporal->getReferenceLog('person', 11, 'position'));
        $this->assertCount(2, $temporal->getReferenceLog('person', 22, 'position'));
        $this->assertCount(0, $temporal->getReferenceLog('person', 33, 'position'));


        $this->assertNull($temporal->getReference('person', 11, 'position', 20170705));
        $this->assertEquals(1, $temporal->getReference('person', 11, 'position', 20170801));
        $this->assertEquals(1, $temporal->getReference('person', 11, 'position', 20170810));
        $this->assertEquals(2, $temporal->getReference('person', 11, 'position', 20170815));
        $this->assertEquals(2, $temporal->getReference('person', 11, 'position', 20170820));
        $this->assertEquals(1, $temporal->getReference('person', 22, 'position', 20170810));
        $this->assertEquals(1, $temporal->getReference('person', 22, 'position', 20170821));
        $this->assertEquals(2, $temporal->getReference('person', 22, 'position', 20170825));
        $this->assertEquals(2, $temporal->getReference('person', 22, 'position', 20170901));

        $this->assertCount(0, $temporal->getReferences('position', 1, 'person', 20170707));
        $this->assertCount(2, $temporal->getReferences('position', 1, 'person', 20170810));
        $this->assertCount(1, $temporal->getReferences('position', 1, 'person', 20170820));
        $this->assertCount(1, $temporal->getReferences('position', 1, 'person', 20170824));
        $this->assertCount(0, $temporal->getReferences('position', 1, 'person', 20170825));
        $this->assertCount(0, $temporal->getReferences('position', 1, 'person', 20170826));
        $this->assertCount(0, $temporal->getReferences('position', 1, 'person', 20170901));
        $this->assertSame($temporal->getReferences('position', 1, 'person', 20170810), [11, 22]);
        $this->assertSame($temporal->getReferences('position', 1, 'person', 20170820), [22]);

        $this->assertCount(0, $temporal->getReferences('position', 2, 'person', 20170810));
        $this->assertCount(1, $temporal->getReferences('position', 2, 'person', 20170820));
        $this->assertCount(2, $temporal->getReferences('position', 2, 'person', 20170825));
        $this->assertCount(2, $temporal->getReferences('position', 2, 'person', 20171231));
        $this->assertSame($temporal->getReferences('position', 2, 'person', 20170820), [11]);
        $this->assertSame($temporal->getReferences('position', 2, 'person', 20170825), [11, 22]);
        $this->assertSame($temporal->getReferences('position', 2, 'person', 20170831), [11, 22]);

        $firstReference = $temporal->findOne('_temporal_reference');
        $temporal->setReferenceIdle('person', $firstReference->id, 'position', $firstReference->targetId, $firstReference->begin, $firstReference->actor, $firstReference->timestamp, true);
        $this->assertNull($temporal->getReference('person', 11, 'position', 20170801));
        $this->assertNull($temporal->getReference('person', 11, 'position', 20170810));
        $this->assertEquals(2, $temporal->getReference('person', 11, 'position', 20170815));
        // idle exists in reference log
        $this->assertCount(2, $temporal->getReferenceLog('person', 11, 'position'));
        $this->assertCount(2, $temporal->getReferenceLog('person', 22, 'position'));

        $temporal->setReferenceIdle('person', $firstReference->id, 'position', $firstReference->targetId, $firstReference->begin, $firstReference->actor, $firstReference->timestamp, false);
        $this->assertSame(1, $temporal->getReference('person', 11, 'position', 20170801));
        $this->assertSame(1, $temporal->getReference('person', 11, 'position', 20170810));
        $this->assertEquals(2, $temporal->getReference('person', 11, 'position', 20170815));
    }

    public function testLinkIdle()
    {
        $temporal = $this->createTemporal();
        $temporal->setActor(1);

        $temporal->link([
            'begin' => 20170801,
            'end' => 20170802,
            'person' => 1,
            'role' => 1,
        ]);

        $temporal->link([
            'begin' => 20170805,
            'end' => 20170806,
            'person' => 1,
            'role' => 1,
        ]);

        $links = $temporal->find('_temporal_link');
        $this->assertCount(3, $links);

        $this->assertCount(1, $temporal->getLinks('person', 1, 20170805));
        $this->assertCount(1, $temporal->getLinks('role', 1, 20170805));

        // last link 0805-0806
        $this->assertSame(date('Ymd', $links[2]->begin), '20170805');

        $temporal->setLinkIdle($links[2]->id, true);

        $log = $temporal->getLinksLog('person', 1);
        $this->assertCount(2, $log);
        $this->assertArrayHasKey('id', $log[0]);
        $this->assertSame($log[0]['idle'], 0);
        $this->assertNotSame($log[1]['idle'], 0);
        $this->assertCount(0, $temporal->getLinks('person', 1, 20170805));
        $this->assertCount(0, $temporal->getLinks('role', 1, 20170805));

        $temporal->setLinkIdle($links[2]->id, false);
        $this->assertCount(1, $temporal->getLinks('person', 1, 20170805));
        $this->assertCount(1, $temporal->getLinks('role', 1, 20170805));
        $log = $temporal->getLinksLog('person', 1);
        $this->assertCount(2, $log);
        $this->assertSame($log[0]['idle'], 0);
        $this->assertSame($log[1]['idle'], 0);
    }

    public function testMultipleLinks()
    {
        $temporal = $this->createTemporal();
        $temporal->setActor(1);

        $temporal->link([
            'begin' => 20170801,
            'end' => 20170802,
            'person' => 1,
            'role' => 1,
        ]);

        $temporal->link([
            'begin' => 20170805,
            'end' => 20170806,
            'person' => 1,
            'role' => 1,
        ]);

        $links = $temporal->find('_temporal_link');
        $this->assertCount(3, $links);
        $this->assertCount(2, $temporal->getLinksLog('person', 1));
    }

    public function testEmptyString()
    {
        $temporal = $this->createTemporal();
        $temporal->setActor(1);

        $temporal->link([
            'begin'  => 0,
            'end'    => "",
            'person' => 1,
            'role'   => 1,
        ]);

        $links = $temporal->find('_temporal_link');
        $this->assertCount(2, $links);
        $target = null;
        foreach ($links as $link) {
            if ($link->actor) {
                $target = $link;
                break;
            }
        }
        $this->assertNotNull($target);
        $this->assertSame($target->begin, 0);
        $this->assertSame($target->end, 0);
    }

    public function testLinkLog()
    {
        $temporal = $this->createTemporal();
        $temporal->setActor(1);

        $temporal->link([
            'begin'  => 0,
            'end'    => 0,
            'person' => 1,
            'role'   => 2,
            'sector' => 3,
        ]);

        Carbon::setTestNow(Carbon::parse("+1 sec"));

        $temporal->link([
            'begin'  => 0,
            'end'    => 0,
            'person' => 1,
            'role'   => 3,
        ]);

        Carbon::setTestNow(Carbon::parse("+2 sec"));

        $temporal->link([
            'begin'  => 0,
            'end'    => 0,
            'person' => 1,
            'sector' => 5,
        ]);

        $this->assertCount(0, $temporal->getLinksLog('person', 2));
        $this->assertCount(3, $temporal->getLinksLog('person', 1));
        $this->assertCount(2, $temporal->getLinksLog('person', 1, ['sector']));
        $this->assertCount(2, $temporal->getLinksLog('person', 1, ['role']));
        $this->assertCount(1, $temporal->getLinksLog('sector', 5));
        $this->assertCount(1, $temporal->getLinksLog('sector', 5, ['person']));
        $this->assertCount(0, $temporal->getLinksLog('sector', 5, ['role']));
    }

    public function testThreeLinks()
    {
        $temporal = $this->createTemporal();
        $temporal->setActor(1);

        $temporal->link([
            'begin'  => 0,
            'end'    => 0,
            'person' => 1,
            'role'   => 2,
            'sector' => 3,
        ]);

        $links = $temporal->getLinks('person', 1, 'now');
        $this->assertCount(1, $links);
        $this->assertArrayNotHasKey('data', $links[0]);

        $links = $temporal->getLinks('person', 1, date('Ymd'));
        $this->assertCount(1, $links);
        $this->assertArrayNotHasKey('data', $links[0]);
    }

    public function testTwoWayLinks()
    {
        $temporal = $this->createTemporal();
        $temporal->setActor(1);

        $temporal->link([
            'person' => 1,
            'role'   => 2,
        ]);

        $links = $temporal->getLinks('person', 1, 'now');
        $this->assertCount(1, $links);
        $this->assertArrayNotHasKey('data', $links[0]);
        $links = $temporal->getLinks('person', 1, date('Ymd'));
        $this->assertCount(1, $links);
        $this->assertArrayNotHasKey('data', $links[0]);
    }

    public function testLinks()
    {
        $temporal = $this->createTemporal();
        $temporal->setActor(1);

        $temporal->link([
            'begin'  => '-1 day',
            'person' => 1,
            'role'   => 2,
            'sector' => 3,
        ]);

        $temporal->link([
            'end'    => '+2 day',
            'person' => 1,
            'role'   => 4,
            'sector' => 3,
        ]);

        $temporal->link([
            'begin'  => '-1 week',
            'end'    => '+1 week',
            'person' => 2,
            'role'   => 22,
            'sector' => 3,
            'data'   => ['superuser' => true],
        ]);

        // link data validation
        $thirdSectorLinksForToday = $temporal->getLinks('sector', 3, 'today');

        $this->assertCount(3, $thirdSectorLinksForToday);

        $superuserLink = null;
        foreach ($thirdSectorLinksForToday as $link) {
            if ($link['person'] == 2) {
                $superuserLink = $link;
            }
        }

        $this->assertNotNull($superuserLink);
        $this->assertArrayHasKey('data', $superuserLink);
        $this->assertArrayHasKey('superuser', $superuserLink['data']);
        $this->assertSame($superuserLink['data']['superuser'], true);
    }

    public function testState()
    {
        $temporal = $this->createTemporal();
        $temporal->setActor(1);

        $temporal->override([
            'post'  => 1,
            'begin' => 'yesterday',
            'end'   => '+2 days',
            'data'  => [
                'title' => 'hello world',
            ]
        ]);

        $this->assertCount(1, $temporal->getOverrides('post', 1));

        Carbon::setTestNow(Carbon::parse("+1 sec"));

        $temporal->override([
            'post'  => 1,
            'begin' => '5 days ago',
            'data'  => [
                'title' => 'test post',
            ]
        ]);

        $this->assertCount(2, $temporal->getOverrides('post', 1));

        $this->assertCount(0, $temporal->getState('post', 1, '1 year ago'));

        foreach (['5 days ago', '-2 days', '+2 days', '+1 year'] as $time) {
            $state = $temporal->getState('post', 1, $time);
            $this->assertArrayHasKey('title', $state, "Validation: $time");
            $this->assertSame($state['title'], 'test post', "Validation: $time");
        }

        foreach (['midnight', 'tomorrow'] as $time) {
            $state = $temporal->getState('post', 1, $time);
            $this->assertArrayHasKey('title', $state);
            $this->assertSame($state['title'], 'hello world', "Validation: $time");
        }

        Carbon::setTestNow(Carbon::parse("+2 sec"));
        $temporal->override([
            'post' => 1,
            'begin' => '+1 day',
            'end' => '+4 days',
            'data' => [
                'title'  => 'new title',
                'notice' => 'my precious'
            ]
        ]);

        foreach (['5 days ago', '-2 days', '+3 year', '+4 days'] as $time) {
            $state = $temporal->getState('post', 1, $time);
            $this->assertArrayHasKey('title', $state);
            $this->assertSame($state['title'], 'test post', "Validation: $time");
        }

        foreach (['+1 day', '+2 days', '+3 days', '+4 days -1 sec'] as $time) {
            $state = $temporal->getState('post', 1, $time);
            $this->assertArrayHasKey('title', $state);
            $this->assertSame($state['title'], 'new title', "Validation: $time");
            $this->assertSame($state['notice'], 'my precious', "Validation: $time");
        }

        foreach (['midnight', 'tomorrow'] as $time) {
            $state = $temporal->getState('post', 1, $time);
            $this->assertArrayHasKey('title', $state);
            $this->assertSame($state['title'], 'hello world', "Validation: $time");
        }

        $override = $temporal->findOne('_temporal_override');
        $this->assertSame($override->data, ['title' => 'test post']);

        $temporal->setOverrideIdle('post', 1, $override->begin, $override->actor, $override->timestamp, true);

        foreach (['5 days ago', '-2 days'] as $time) {
            $state = $temporal->getState('post', 1, $time);
            $this->assertArrayNotHasKey('title', $state);
        }

        foreach (['+1 day', '+2 days', '+3 days', '+4 days -1 sec'] as $time) {
            $state = $temporal->getState('post', 1, $time);
            $this->assertArrayHasKey('title', $state);
            $this->assertSame($state['title'], 'new title', "Validation: $time");
            $this->assertSame($state['notice'], 'my precious', "Validation: $time");
        }

        foreach (['midnight', 'tomorrow'] as $time) {
            $state = $temporal->getState('post', 1, $time);
            $this->assertArrayHasKey('title', $state);
            $this->assertSame($state['title'], 'hello world', "Validation: $time");
        }
    }

    public function testStateComplex()
    {
        $temporal = $this->createTemporal();
        $temporal->setActor(1);

        $temporal->override([
            'post'  => 1,
            'begin' => 20170801,
            'data'  => ['key1' => 20170801]
        ]);
        $temporal->override([
            'post'  => 1,
            'begin' => 20170802,
            'data'  => ['key2' => 20170802]
        ]);
        $this->assertCount(2, $temporal->find('_temporal_override_aggregate'));

        $temporal->override([
            'post'  => 1,
            'begin' => 20170803,
            'data'  => ['key1' => 20170803]
        ]);
        $temporal->override([
            'post'  => 1,
            'begin' => 20170805,
            'data'  => ['key1' => 20170805]
        ]);

        $temporal->override([
            'post'  => 1,
            'begin' => 20170804,
            'data'  => ['key2' => 20170804]
        ]);
        $temporal->override([
            'post'  => 1,
            'begin' => 20170806,
            'data'  => ['key2' => 20170806]
        ]);

        Carbon::setTestNow(Carbon::parse("+1 sec"));

        // [20170804, 20170805]
        $temporal->override([
            'post' => 1,
            'begin' => 20170804,
            'end' => 20170806,
            'data' => ['period' => 'x'],
        ]);

        $this->assertSame($temporal->getState('post', 1, 20170801), [
            'key1' => 20170801
        ]);

        $this->assertSame($temporal->getState('post', 1, 20170802), [
            'key1' => 20170801,
            'key2' => 20170802,
        ]);
        $this->assertSame($temporal->getState('post', 1, 20170803), [
            'key1' => 20170803,
            'key2' => 20170802,
        ]);
        $this->assertSame($temporal->getState('post', 1, 20170804), [
            'key1' => 20170803,
            'key2' => 20170804,
            'period' => 'x',
        ]);
        $this->assertSame($temporal->getState('post', 1, 20170805), [
            'key1' => 20170805,
            'key2' => 20170804,
            'period' => 'x',
        ]);
        $this->assertSame($temporal->getState('post', 1, 20170806), [
            'key1' => 20170805,
            'key2' => 20170806,
        ]);
    }

    public function testReferenceStateAggregation()
    {
        $temporal = $this->createTemporal();
        $temporal->setActor(1);

        $temporal->reference([
            'begin' => 20190114,
            'end' => 20190115,
            'person' => 27,
            'data' => [
                'position' => 2
            ]
        ]);

        $temporal->reference([
            'begin' => 20190121,
            'end' => 20190122,
            'person' => 27,
            'data' => [
                'position' => 2
            ]
        ]);

        $this->assertCount(2, $temporal->find('_temporal_reference_state'));

        $temporal->reference([
            'begin' => 20190115,
            'end' => 20190119,
            'person' => 27,
            'data' => [
                'position' => 2
            ]
        ]);
        $this->assertCount(2, $temporal->find('_temporal_reference_state'));

        $temporal->reference([
            'begin' => 20190119,
            'end' => 20190121,
            'person' => 27,
            'data' => [
                'position' => 2
            ]
        ]);
        $this->assertCount(1, $temporal->find('_temporal_reference_state'));
    }
}