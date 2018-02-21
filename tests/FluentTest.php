<?php

use Docs\Doc1;

class FluentTest extends PHPUnit\Framework\TestCase
{
    public function testFluentWithDynamicSet()
    {
        $query = User::find();
        $query->email = 'foobar@gmail.com';

        $this->assertEquals(['email' => 'foobar@gmail.com'], $query->getFilter());
    }

    public function testFluentWithDynamicSetStr()
    {
        $query = User::find();
        $query->{'x.email'} = 'foobar@gmail.com';

        $this->assertEquals(['x.email' => 'foobar@gmail.com'], $query->getFilter());
    }

    public function testFluentWithDynamicNested()
    {
        $query = User::find();
        $query->x->email = 'foobar@gmail.com';

        $this->assertEquals(['x.email' => 'foobar@gmail.com'], $query->getFilter());
    }

    public function testEmpty()
    {
        $query = User::find();
        $this->assertTrue(empty($query['foo']));
        $query->foo = 1;
        $this->assertFalse(empty($query['foo']));
    }

    public function testUnset()
    {
        $query = User::find();
        $this->assertTrue(empty($query['foo']));
        $query->foo = 1;
        $this->assertFalse(empty($query['foo']));
        unset($query['foo']);
        $this->assertTrue(empty($query['foo']));
    }

    public function testFluentWithDynamicNestedFunction()
    {
        $query = User::find();
        $query->x->y->email->is('foobar@gmail.com');

        $this->assertEquals(['x.y.email' => 'foobar@gmail.com'], $query->getFilter());
    }

    public function testFluentWithDynamicSetArray()
    {
        $query = User::find();
        $query['x.email'] = 'foobar@gmail.com';

        $this->assertEquals(['x.email' => 'foobar@gmail.com'], $query->getFilter());
    }

    public function testWhere()
    {
        $query = User::find();
        $query->where('email', 'foobar@gmail.com')
            ->where('size', '$gt', 10);

        $this->assertEquals([
            'email' => 'foobar@gmail.com',
            'size'  => ['$gt' => 10],
        ], $query->getFilter());
    }

    public function testDynamicPRoperty()
    {
        $query = User::find();
        $query->email->is('foobar@gmail.com')
            ->size->gt(10);

        $this->assertEquals([
            'email' => 'foobar@gmail.com',
            'size'  => ['$gt' => 10],
        ], $query->getFilter());
    }

    public function testBetween()
    {
        $query = User::find();
        $query->email->is('foobar@gmail.com')
            ->size->between(5, 10);

        $this->assertEquals([
            'email' => 'foobar@gmail.com',
            'size'  => ['$gte' => 5, '$lte' => 10],
        ], $query->getFilter());
    }

    public function testSize()
    {
        $query = User::find()->prop->size(5);
        $this->assertEquals([
            'prop'  => ['$size' => 5],
        ], $query->getFilter());
    }

    public function testType()
    {
        $query = User::find()->prop->type('double');
        $this->assertEquals([
            'prop'  => ['$type' => 'double'],
        ], $query->getFilter());
    }

    public function testExists()
    {
        $query = User::find()->prop->exists();
        $this->assertEquals([
            'prop'  => ['$exists' => true],
        ], $query->getFilter());
    }

    public function testIsNot()
    {
        $query = User::find();
        $query->email->isNot('foobar@gmail.com');

        $this->assertEquals(['email' => ['$ne' => 'foobar@gmail.com']], $query->getFilter());
    }

    public function testAll()
    {
        $query = User::find();
        $query->email->all(['foobar@gmail.com', 'bar@gmail.com']);

        $this->assertEquals(['email' => ['$all' => ['foobar@gmail.com', 'bar@gmail.com']]], $query->getFilter());
    }

    public function testIn()
    {
        $query = User::find();
        $query->email->in(['foobar@gmail.com', 'bar@gmail.com']);

        $this->assertEquals(['email' => ['$in' => ['foobar@gmail.com', 'bar@gmail.com']]], $query->getFilter());
    }

    public function testNotIn()
    {
        $query = User::find();
        $query->email->notIn(['foobar@gmail.com', 'bar@gmail.com']);

        $this->assertEquals(['email' => ['$nin' => ['foobar@gmail.com', 'bar@gmail.com']]], $query->getFilter());
    }

    public function testOrLogicBlock()
    {
        $query = User::find()
            ->or(function($q) {
                $q->is_active = 1;
                $q->is_allowed = 1;
            }, function($q) {
                $q->is_admin = 1;
            });

        $this->assertEquals([
            '$or' => [
                ['is_active' => 1, 'is_allowed' => 1],
                ['is_admin' => 1],
            ],
        ], $query->getFilter());
    }

    public function testOrLogicBlock1()
    {
        $query = User::find()
            ->or_(function($q) {
                $q->is_active = 1;
                $q->is_allowed = 1;
            }, function($q) {
                $q->is_admin = 1;
            });

        $this->assertEquals([
            '$or' => [
                ['is_active' => 1, 'is_allowed' => 1],
                ['is_admin' => 1],
            ],
        ], $query->getFilter());
    }

    public function testAndLogicBlock1()
    {
        $query = User::find()
            ->and_(function($q) {
                $q->is_active = 1;
                $q->is_allowed = 1;
            }, function($q) {
                $q->is_admin = 1;
            });

        $this->assertEquals([
            '$and' => [
                ['is_active' => 1, 'is_allowed' => 1],
                ['is_admin' => 1],
            ],
        ], $query->getFilter());
    }

    public function testAndLogicBlock()
    {
        $query = User::find()
            ->and(function($q) {
                $q->is_active = 1;
                $q->is_allowed = 1;
            }, function($q) {
                $q->is_admin = 1;
            });

        $this->assertEquals([
            '$and' => [
                ['is_active' => 1, 'is_allowed' => 1],
                ['is_admin' => 1],
            ],
        ], $query->getFilter());
    }

    public function testNorLogicBlock()
    {
        $query = User::find()
            ->nor(function($q) {
                $q->is_active = 1;
                $q->is_allowed = 1;
            }, function($q) {
                $q->is_admin = 1;
            });

        $this->assertEquals([
            '$nor' => [
                ['is_active' => 1, 'is_allowed' => 1],
                ['is_admin' => 1],
            ],
        ], $query->getFilter());
    }

    public function testNorLogicBlock1()
    {
        $query = User::find()
            ->nor_(function($q) {
                $q->is_active = 1;
                $q->is_allowed = 1;
            }, function($q) {
                $q->is_admin = 1;
            });

        $this->assertEquals([
            '$nor' => [
                ['is_active' => 1, 'is_allowed' => 1],
                ['is_admin' => 1],
            ],
        ], $query->getFilter());
    }

    public function testMathALias()
    {
        $query = User::find()
            ->and(function($q) {
                $q->where('age', '>=', 5);
                $q->xxx->gte(5);
            }, function($q) {
                $q->where('age', '<=', 99);
                $q->xxx->lt(5);
                $q->yyy->lte(5);
            });

        $this->assertEquals([
            '$and' => [
                ['age' => ['$gte' => 5], 'xxx' => ['$gte' => 5]],
                ['age' => ['$lte' => 99], 'xxx' => ['$lt' => 5], 'yyy' => ['$lte' => 5]],
            ],
        ], $query->getFilter());
    }

    public function testWhereNoDollar()
    {
        $query = User::find()->where('foo', 'NIN', [1,2]);
        $this->assertEquals(['foo' => ['$nin' => [1,2]]], $query->getFilter());
    }

    public function testNotExpr()
    {
        $query = User::find()->not(function($q) {
            $q->f = 1;
        });
        $this->assertEquals(['f' => ['$not' => 1]], $query->getFilter());
    }

    /**
     * @expectedException RuntimeException
     */
    public function testInvalidDynamicCall()
    {
        USer::find()->dasdsada();
    }

    public function testUpdateFilterSetter()
    {
        $query = Doc1::update(function($filter) {
                $filter->foo = 'bar';
            })->set(function($doc) {
                $doc->foo = 'new';
                $doc->counter->add(10);
            });

        $this->assertEquals(['foo' => 'bar'], $query->GetFilter());
        $this->assertEquals(['$set' => ['foo' => 'new'], '$inc' => ['counter' => 10]], $query->getUpdateDocument());
    }

    public function testWhereFieldName()
    {
        $q = Doc1::find()->where('foo')->is(1);
        $this->AssertEquals(['foo' => 1], $q->getFilter());
    }

    /**
     * @expectedException RuntimeException
     */
    public function testInvalidCallWhere()
    {
        Doc1::find()->where();
    }
}

