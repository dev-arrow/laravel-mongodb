<?php

class EmbeddedRelationsTest extends TestCase
{
    public function tearDown()
    {
        Mockery::close();

        User::truncate();
        Book::truncate();
        Item::truncate();
        Role::truncate();
        Client::truncate();
        Group::truncate();
        Photo::truncate();
    }

    public function testEmbedsManySave()
    {
        $user = User::create(['name' => 'John Doe']);
        $address = new Address(['city' => 'London']);

        $address = $user->addresses()->save($address);

        $this->assertNotNull($user->addresses);
        $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $user->addresses);
        $this->assertEquals(['London'], $user->addresses->pluck('city')->all());
        $this->assertInstanceOf('DateTime', $address->created_at);
        $this->assertInstanceOf('DateTime', $address->updated_at);
        $this->assertNotNull($address->_id);
        $this->assertTrue(is_string($address->_id));

        $raw = $address->getAttributes();
        $this->assertInstanceOf('MongoDB\BSON\ObjectID', $raw['_id']);

        $address = $user->addresses()->save(new Address(['city' => 'Paris']));

        $user = User::find($user->_id);
        $this->assertEquals(['London', 'Paris'], $user->addresses->pluck('city')->all());

        $address->city = 'New York';
        $user->addresses()->save($address);

        $this->assertEquals(2, count($user->addresses));
        $this->assertEquals(2, count($user->addresses()->get()));
        $this->assertEquals(2, $user->addresses->count());
        $this->assertEquals(2, $user->addresses()->count());
        $this->assertEquals(['London', 'New York'], $user->addresses->pluck('city')->all());

        $freshUser = User::find($user->_id);
        $this->assertEquals(['London', 'New York'], $freshUser->addresses->pluck('city')->all());

        $address = $user->addresses->first();
        $this->assertEquals('London', $address->city);
        $this->assertInstanceOf('DateTime', $address->created_at);
        $this->assertInstanceOf('DateTime', $address->updated_at);
        $this->assertInstanceOf('User', $address->user);
        $this->assertEmpty($address->relationsToArray()); // prevent infinite loop

        $user = User::find($user->_id);
        $user->addresses()->save(new Address(['city' => 'Bruxelles']));
        $this->assertEquals(['London', 'New York', 'Bruxelles'], $user->addresses->pluck('city')->all());

        $address = $user->addresses[1];
        $address->city = "Manhattan";
        $user->addresses()->save($address);
        $this->assertEquals(['London', 'Manhattan', 'Bruxelles'], $user->addresses->pluck('city')->all());

        $freshUser = User::find($user->_id);
        $this->assertEquals(['London', 'Manhattan', 'Bruxelles'], $freshUser->addresses->pluck('city')->all());
    }

    // public function testEmbedsManySaveModel()
    // {
    //     $user = User::create(['name' => 'John Doe']);
    //     $address = new Address(['city' => 'London']);

    //     $address->setEventDispatcher($events = Mockery::mock('Illuminate\Events\Dispatcher'));
    //     $events->shouldReceive('until')->once()->with('eloquent.saving: ' . get_class($address), $address)->andReturn(true);
    //     $events->shouldReceive('until')->once()->with('eloquent.creating: ' . get_class($address), $address)->andReturn(true);
    //     $events->shouldReceive('fire')->once()->with('eloquent.created: ' . get_class($address), $address);
    //     $events->shouldReceive('fire')->once()->with('eloquent.saved: ' . get_class($address), $address);

    //     $address->save();

    //     $address->setEventDispatcher($events = Mockery::mock('Illuminate\Events\Dispatcher'));
    //     $events->shouldReceive('until')->once()->with('eloquent.saving: ' . get_class($address), $address)->andReturn(true);
    //     $events->shouldReceive('until')->once()->with('eloquent.updating: ' . get_class($address), $address)->andReturn(true);
    //     $events->shouldReceive('fire')->once()->with('eloquent.updated: ' . get_class($address), $address);
    //     $events->shouldReceive('fire')->once()->with('eloquent.saved: ' . get_class($address), $address);

    //     $address->city = 'Paris';
    //     $address->save();
    // }

    public function testEmbedsToArray()
    {
        $user = User::create(['name' => 'John Doe']);
        $user->addresses()->saveMany([new Address(['city' => 'London']), new Address(['city' => 'Bristol'])]);

        $array = $user->toArray();
        $this->assertFalse(array_key_exists('_addresses', $array));
        $this->assertTrue(array_key_exists('addresses', $array));
    }

    public function testEmbedsManyAssociate()
    {
        $user = User::create(['name' => 'John Doe']);
        $address = new Address(['city' => 'London']);

        $user->addresses()->associate($address);
        $this->assertEquals(['London'], $user->addresses->pluck('city')->all());
        $this->assertNotNull($address->_id);

        $freshUser = User::find($user->_id);
        $this->assertEquals([], $freshUser->addresses->pluck('city')->all());

        $address->city = 'Londinium';
        $user->addresses()->associate($address);
        $this->assertEquals(['Londinium'], $user->addresses->pluck('city')->all());

        $freshUser = User::find($user->_id);
        $this->assertEquals([], $freshUser->addresses->pluck('city')->all());
    }

    public function testEmbedsManySaveMany()
    {
        $user = User::create(['name' => 'John Doe']);
        $user->addresses()->saveMany([new Address(['city' => 'London']), new Address(['city' => 'Bristol'])]);
        $this->assertEquals(['London', 'Bristol'], $user->addresses->pluck('city')->all());

        $freshUser = User::find($user->id);
        $this->assertEquals(['London', 'Bristol'], $freshUser->addresses->pluck('city')->all());
    }

    public function testEmbedsManyDuplicate()
    {
        $user = User::create(['name' => 'John Doe']);
        $address = new Address(['city' => 'London']);
        $user->addresses()->save($address);
        $user->addresses()->save($address);
        $this->assertEquals(1, $user->addresses->count());
        $this->assertEquals(['London'], $user->addresses->pluck('city')->all());

        $user = User::find($user->id);
        $this->assertEquals(1, $user->addresses->count());

        $address->city = 'Paris';
        $user->addresses()->save($address);
        $this->assertEquals(1, $user->addresses->count());
        $this->assertEquals(['Paris'], $user->addresses->pluck('city')->all());

        $user->addresses()->create(['_id' => $address->_id, 'city' => 'Bruxelles']);
        $this->assertEquals(1, $user->addresses->count());
        $this->assertEquals(['Bruxelles'], $user->addresses->pluck('city')->all());
    }

    public function testEmbedsManyCreate()
    {
        $user = User::create([]);
        $address = $user->addresses()->create(['city' => 'Bruxelles']);
        $this->assertInstanceOf('Address', $address);
        $this->assertTrue(is_string($address->_id));
        $this->assertEquals(['Bruxelles'], $user->addresses->pluck('city')->all());

        $raw = $address->getAttributes();
        $this->assertInstanceOf('MongoDB\BSON\ObjectID', $raw['_id']);

        $freshUser = User::find($user->id);
        $this->assertEquals(['Bruxelles'], $freshUser->addresses->pluck('city')->all());

        $user = User::create([]);
        $address = $user->addresses()->create(['_id' => '', 'city' => 'Bruxelles']);
        $this->assertTrue(is_string($address->_id));

        $raw = $address->getAttributes();
        $this->assertInstanceOf('MongoDB\BSON\ObjectID', $raw['_id']);
    }

    public function testEmbedsManyCreateMany()
    {
        $user = User::create([]);
        list($bruxelles, $paris) = $user->addresses()->createMany([['city' => 'Bruxelles'], ['city' => 'Paris']]);
        $this->assertInstanceOf('Address', $bruxelles);
        $this->assertEquals('Bruxelles', $bruxelles->city);
        $this->assertEquals(['Bruxelles', 'Paris'], $user->addresses->pluck('city')->all());

        $freshUser = User::find($user->id);
        $this->assertEquals(['Bruxelles', 'Paris'], $freshUser->addresses->pluck('city')->all());
    }

    public function testEmbedsManyDestroy()
    {
        $user = User::create(['name' => 'John Doe']);
        $user->addresses()->saveMany([new Address(['city' => 'London']), new Address(['city' => 'Bristol']), new Address(['city' => 'Bruxelles'])]);

        $address = $user->addresses->first();

        $user->addresses()->destroy($address->_id);
        $this->assertEquals(['Bristol', 'Bruxelles'], $user->addresses->pluck('city')->all());

        $address = $user->addresses->first();
        $user->addresses()->destroy($address);
        $this->assertEquals(['Bruxelles'], $user->addresses->pluck('city')->all());

        $user->addresses()->create(['city' => 'Paris']);
        $user->addresses()->create(['city' => 'San Francisco']);

        $freshUser = User::find($user->id);
        $this->assertEquals(['Bruxelles', 'Paris', 'San Francisco'], $freshUser->addresses->pluck('city')->all());

        $ids = $user->addresses->pluck('_id');
        $user->addresses()->destroy($ids);
        $this->assertEquals([], $user->addresses->pluck('city')->all());

        $freshUser = User::find($user->id);
        $this->assertEquals([], $freshUser->addresses->pluck('city')->all());

        list($london, $bristol, $bruxelles) = $user->addresses()->saveMany([new Address(['city' => 'London']), new Address(['city' => 'Bristol']), new Address(['city' => 'Bruxelles'])]);
        $user->addresses()->destroy([$london, $bruxelles]);
        $this->assertEquals(['Bristol'], $user->addresses->pluck('city')->all());
    }

    public function testEmbedsManyDelete()
    {
        $user = User::create(['name' => 'John Doe']);
        $user->addresses()->saveMany([new Address(['city' => 'London']), new Address(['city' => 'Bristol']), new Address(['city' => 'Bruxelles'])]);

        $address = $user->addresses->first();

        $address->delete();

        $this->assertEquals(2, $user->addresses()->count());
        $this->assertEquals(2, $user->addresses->count());

        $address = $user->addresses->first();
        $address->delete();

        $user = User::where('name', 'John Doe')->first();
        $this->assertEquals(1, $user->addresses()->count());
        $this->assertEquals(1, $user->addresses->count());
    }

    public function testEmbedsManyDissociate()
    {
        $user = User::create([]);
        $cordoba = $user->addresses()->create(['city' => 'Cordoba']);

        $user->addresses()->dissociate($cordoba->id);

        $freshUser = User::find($user->id);
        $this->assertEquals(0, $user->addresses->count());
        $this->assertEquals(1, $freshUser->addresses->count());
    }

    public function testEmbedsManyAliases()
    {
        $user = User::create(['name' => 'John Doe']);
        $address = new Address(['city' => 'London']);

        $address = $user->addresses()->attach($address);
        $this->assertEquals(['London'], $user->addresses->pluck('city')->all());

        $user->addresses()->detach($address);
        $this->assertEquals([], $user->addresses->pluck('city')->all());
    }

    public function testEmbedsManyCreatingEventReturnsFalse()
    {
        $user = User::create(['name' => 'John Doe']);
        $address = new Address(['city' => 'London']);

        $address::creating(function () {
            return false;
        });

        $this->assertFalse($user->addresses()->save($address));
    }

    public function testEmbedsManySavingEventReturnsFalse()
    {
        $user = User::create(['name' => 'John Doe']);
        $address = new Address(['city' => 'Paris']);
        $address->exists = true;

        $address::saving(function () {
            return false;
        });

        $this->assertFalse($user->addresses()->save($address));
    }

    public function testEmbedsManyUpdatingEventReturnsFalse()
    {
        $user = User::create(['name' => 'John Doe']);
        $address = new Address(['city' => 'New York']);
        $user->addresses()->save($address);

        $address::updating(function () {
            return false;
        });

        $address->city = 'Warsaw';

        $this->assertFalse($user->addresses()->save($address));
    }

    public function testEmbedsManyDeletingEventReturnsFalse()
    {
        $user = User::create(['name' => 'John Doe']);
        $user->addresses()->save(new Address(['city' => 'New York']));

        $address = $user->addresses->first();

        $address::deleting(function () {
            return false;
        });

        $this->assertEquals(0, $user->addresses()->destroy($address));
        $this->assertEquals(['New York'], $user->addresses->pluck('city')->all());
    }

    public function testEmbedsManyFindOrContains()
    {
        $user = User::create(['name' => 'John Doe']);
        $address1 = $user->addresses()->save(new Address(['city' => 'New York']));
        $address2 = $user->addresses()->save(new Address(['city' => 'Paris']));

        $address = $user->addresses()->find($address1->_id);
        $this->assertEquals($address->city, $address1->city);

        $address = $user->addresses()->find($address2->_id);
        $this->assertEquals($address->city, $address2->city);

        $this->assertTrue($user->addresses()->contains($address2->_id));
        $this->assertFalse($user->addresses()->contains('123'));
    }

    public function testEmbedsManyEagerLoading()
    {
        $user1 = User::create(['name' => 'John Doe']);
        $user1->addresses()->save(new Address(['city' => 'New York']));
        $user1->addresses()->save(new Address(['city' => 'Paris']));

        $user2 = User::create(['name' => 'Jane Doe']);
        $user2->addresses()->save(new Address(['city' => 'Berlin']));
        $user2->addresses()->save(new Address(['city' => 'Paris']));

        $user = User::find($user1->id);
        $relations = $user->getRelations();
        $this->assertFalse(array_key_exists('addresses', $relations));
        $this->assertArrayHasKey('addresses', $user->toArray());
        $this->assertTrue(is_array($user->toArray()['addresses']));

        $user = User::with('addresses')->get()->first();
        $relations = $user->getRelations();
        $this->assertTrue(array_key_exists('addresses', $relations));
        $this->assertEquals(2, $relations['addresses']->count());
        $this->assertArrayHasKey('addresses', $user->toArray());
        $this->assertTrue(is_array($user->toArray()['addresses']));
    }

    public function testEmbedsManyDeleteAll()
    {
        $user1 = User::create(['name' => 'John Doe']);
        $user1->addresses()->save(new Address(['city' => 'New York']));
        $user1->addresses()->save(new Address(['city' => 'Paris']));

        $user2 = User::create(['name' => 'Jane Doe']);
        $user2->addresses()->save(new Address(['city' => 'Berlin']));
        $user2->addresses()->save(new Address(['city' => 'Paris']));

        $user1->addresses()->delete();
        $this->assertEquals(0, $user1->addresses()->count());
        $this->assertEquals(0, $user1->addresses->count());
        $this->assertEquals(2, $user2->addresses()->count());
        $this->assertEquals(2, $user2->addresses->count());

        $user1 = User::find($user1->id);
        $user2 = User::find($user2->id);
        $this->assertEquals(0, $user1->addresses()->count());
        $this->assertEquals(0, $user1->addresses->count());
        $this->assertEquals(2, $user2->addresses()->count());
        $this->assertEquals(2, $user2->addresses->count());
    }

    public function testEmbedsManyCollectionMethods()
    {
        $user = User::create(['name' => 'John Doe']);
        $user->addresses()->save(new Address(['city' => 'Paris', 'country' => 'France', 'visited' => 4, 'created_at' => new DateTime('3 days ago')]));
        $user->addresses()->save(new Address(['city' => 'Bruges', 'country' => 'Belgium', 'visited' => 7, 'created_at' => new DateTime('5 days ago')]));
        $user->addresses()->save(new Address(['city' => 'Brussels', 'country' => 'Belgium', 'visited' => 2, 'created_at' => new DateTime('4 days ago')]));
        $user->addresses()->save(new Address(['city' => 'Ghent', 'country' => 'Belgium', 'visited' => 13, 'created_at' => new DateTime('2 days ago')]));

        $this->assertEquals(['Paris', 'Bruges', 'Brussels', 'Ghent'], $user->addresses()->pluck('city')->all());
        $this->assertEquals(['Bruges', 'Brussels', 'Ghent', 'Paris'], $user->addresses()->sortBy('city')->pluck('city')->all());
        $this->assertEquals([], $user->addresses()->where('city', 'New York')->pluck('city')->all());
        $this->assertEquals(['Bruges', 'Brussels', 'Ghent'], $user->addresses()->where('country', 'Belgium')->pluck('city')->all());
        $this->assertEquals(['Bruges', 'Brussels', 'Ghent'], $user->addresses()->where('country', 'Belgium')->sortBy('city')->pluck('city')->all());

        $results = $user->addresses->first();
        $this->assertInstanceOf('Address', $results);

        $results = $user->addresses()->where('country', 'Belgium');
        $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $results);
        $this->assertEquals(3, $results->count());

        $results = $user->addresses()->whereIn('visited', [7, 13]);
        $this->assertEquals(2, $results->count());
    }

    public function testEmbedsOne()
    {
        $user = User::create(['name' => 'John Doe']);
        $father = new User(['name' => 'Mark Doe']);

        $father = $user->father()->save($father);

        $this->assertNotNull($user->father);
        $this->assertEquals('Mark Doe', $user->father->name);
        $this->assertInstanceOf('DateTime', $father->created_at);
        $this->assertInstanceOf('DateTime', $father->updated_at);
        $this->assertNotNull($father->_id);
        $this->assertTrue(is_string($father->_id));

        $raw = $father->getAttributes();
        $this->assertInstanceOf('MongoDB\BSON\ObjectID', $raw['_id']);

        $father->name = 'Tom Doe';
        $user->father()->save($father);

        $this->assertNotNull($user->father);
        $this->assertEquals('Tom Doe', $user->father->name);

        $father = new User(['name' => 'Jim Doe']);

        $father = $user->father()->save($father);

        $this->assertNotNull($user->father);
        $this->assertEquals('Jim Doe', $user->father->name);
    }

    public function testEmbedsOneAssociate()
    {
        $user = User::create(['name' => 'John Doe']);
        $father = new User(['name' => 'Mark Doe']);

        $father = $user->father()->associate($father);

        $this->assertNotNull($user->father);
        $this->assertEquals('Mark Doe', $user->father->name);
    }

    public function testEmbedsOneNullAssociation()
    {
        $user = User::create();
        $this->assertNull($user->father);
    }

    public function testEmbedsOneDelete()
    {
        $user = User::create(['name' => 'John Doe']);
        $father = $user->father()->save(new User(['name' => 'Mark Doe']));

        $user->father()->delete();
        $this->assertNull($user->father);
    }

    public function testEmbedsManyToArray()
    {
        $user = User::create(['name' => 'John Doe']);
        $user->addresses()->save(new Address(['city' => 'New York']));
        $user->addresses()->save(new Address(['city' => 'Paris']));
        $user->addresses()->save(new Address(['city' => 'Brussels']));

        $array = $user->toArray();
        $this->assertArrayHasKey('addresses', $array);
        $this->assertTrue(is_array($array['addresses']));
    }

    public function testEmbeddedSave()
    {
        $user = User::create(['name' => 'John Doe']);
        $address = $user->addresses()->create(['city' => 'New York']);
        $father = $user->father()->create(['name' => 'Mark Doe']);

        $address->city = 'Paris';
        $address->save();

        $father->name = 'Steve Doe';
        $father->save();

        $this->assertEquals('Paris', $user->addresses->first()->city);
        $this->assertEquals('Steve Doe', $user->father->name);

        $user = User::where('name', 'John Doe')->first();
        $this->assertEquals('Paris', $user->addresses->first()->city);
        $this->assertEquals('Steve Doe', $user->father->name);

        $address = $user->addresses()->first();
        $father = $user->father;

        $address->city = 'Ghent';
        $address->save();

        $father->name = 'Mark Doe';
        $father->save();

        $this->assertEquals('Ghent', $user->addresses->first()->city);
        $this->assertEquals('Mark Doe', $user->father->name);

        $user = User::where('name', 'John Doe')->first();
        $this->assertEquals('Ghent', $user->addresses->first()->city);
        $this->assertEquals('Mark Doe', $user->father->name);
    }

    public function testNestedEmbedsOne()
    {
        $user = User::create(['name' => 'John Doe']);
        $father = $user->father()->create(['name' => 'Mark Doe']);
        $grandfather = $father->father()->create(['name' => 'Steve Doe']);
        $greatgrandfather = $grandfather->father()->create(['name' => 'Tom Doe']);

        $user->name = 'Tim Doe';
        $user->save();

        $father->name = 'Sven Doe';
        $father->save();

        $greatgrandfather->name = 'Ron Doe';
        $greatgrandfather->save();

        $this->assertEquals('Tim Doe', $user->name);
        $this->assertEquals('Sven Doe', $user->father->name);
        $this->assertEquals('Steve Doe', $user->father->father->name);
        $this->assertEquals('Ron Doe', $user->father->father->father->name);

        $user = User::where('name', 'Tim Doe')->first();
        $this->assertEquals('Tim Doe', $user->name);
        $this->assertEquals('Sven Doe', $user->father->name);
        $this->assertEquals('Steve Doe', $user->father->father->name);
        $this->assertEquals('Ron Doe', $user->father->father->father->name);
    }

    public function testNestedEmbedsMany()
    {
        $user = User::create(['name' => 'John Doe']);
        $country1 = $user->addresses()->create(['country' => 'France']);
        $country2 = $user->addresses()->create(['country' => 'Belgium']);
        $city1 = $country1->addresses()->create(['city' => 'Paris']);
        $city2 = $country2->addresses()->create(['city' => 'Ghent']);
        $city3 = $country2->addresses()->create(['city' => 'Brussels']);

        $city3->city = 'Bruges';
        $city3->save();

        $this->assertEquals(2, $user->addresses()->count());
        $this->assertEquals(1, $user->addresses()->first()->addresses()->count());
        $this->assertEquals(2, $user->addresses()->last()->addresses()->count());

        $user = User::where('name', 'John Doe')->first();
        $this->assertEquals(2, $user->addresses()->count());
        $this->assertEquals(1, $user->addresses()->first()->addresses()->count());
        $this->assertEquals(2, $user->addresses()->last()->addresses()->count());
    }

    public function testNestedMixedEmbeds()
    {
        $user = User::create(['name' => 'John Doe']);
        $father = $user->father()->create(['name' => 'Mark Doe']);
        $country1 = $father->addresses()->create(['country' => 'France']);
        $country2 = $father->addresses()->create(['country' => 'Belgium']);

        $country2->country = 'England';
        $country2->save();

        $father->name = 'Steve Doe';
        $father->save();

        $this->assertEquals('France', $user->father->addresses()->first()->country);
        $this->assertEquals('England', $user->father->addresses()->last()->country);
        $this->assertEquals('Steve Doe', $user->father->name);

        $user = User::where('name', 'John Doe')->first();
        $this->assertEquals('France', $user->father->addresses()->first()->country);
        $this->assertEquals('England', $user->father->addresses()->last()->country);
        $this->assertEquals('Steve Doe', $user->father->name);
    }

    public function testDoubleAssociate()
    {
        $user = User::create(['name' => 'John Doe']);
        $address = new Address(['city' => 'Paris']);

        $user->addresses()->associate($address);
        $user->addresses()->associate($address);
        $address = $user->addresses()->first();
        $user->addresses()->associate($address);
        $this->assertEquals(1, $user->addresses()->count());

        $user = User::where('name', 'John Doe')->first();
        $user->addresses()->associate($address);
        $this->assertEquals(1, $user->addresses()->count());

        $user->save();
        $user->addresses()->associate($address);
        $this->assertEquals(1, $user->addresses()->count());
    }

    public function testSaveEmptyModel()
    {
        $user = User::create(['name' => 'John Doe']);
        $user->addresses()->save(new Address);
        $this->assertNotNull($user->addresses);
        $this->assertEquals(1, $user->addresses()->count());
    }

    public function testIncrementEmbedded()
    {
        $user = User::create(['name' => 'John Doe']);
        $address = $user->addresses()->create(['city' => 'New York', 'visited' => 5]);

        $address->increment('visited');
        $this->assertEquals(6, $address->visited);
        $this->assertEquals(6, $user->addresses()->first()->visited);

        $user = User::where('name', 'John Doe')->first();
        $this->assertEquals(6, $user->addresses()->first()->visited);

        $user = User::where('name', 'John Doe')->first();
        $address = $user->addresses()->first();

        $address->decrement('visited');
        $this->assertEquals(5, $address->visited);
        $this->assertEquals(5, $user->addresses()->first()->visited);

        $user = User::where('name', 'John Doe')->first();
        $this->assertEquals(5, $user->addresses()->first()->visited);
    }

    public function testPaginateEmbedsMany()
    {
        $user = User::create(['name' => 'John Doe']);
        $user->addresses()->save(new Address(['city' => 'New York']));
        $user->addresses()->save(new Address(['city' => 'Paris']));
        $user->addresses()->save(new Address(['city' => 'Brussels']));

        $results = $user->addresses()->paginate(2);
        $this->assertEquals(2, $results->count());
        $this->assertEquals(3, $results->total());
    }
}
