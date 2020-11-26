<?php

declare(strict_types=1);

/*
 * This file is part of Exchanger.
 *
 * (c) Florian Voutzinos <florian@voutzinos.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Exchanger\Tests;

use Exchanger\StringUtil;
use PHPUnit\Framework\TestCase;

class StringUtilTest extends TestCase
{
    /**
     * @test
     */
    public function it_converts_an_xml_string_to_element()
    {
        $element = StringUtil::xmlToElement('<root>hello</root>');

        $this->assertInstanceOf('\SimpleXMLElement', $element);
        $this->assertEquals('hello', (string) $element);
    }

    /**
     * @test
     */
    public function it_throws_an_exception_when_converting_invalid_xml()
    {
        $this->expectException(\RuntimeException::class);
        StringUtil::xmlToElement('/');
    }

    /**
     * @test
     */
    public function it_converts_a_json_string_to_array()
    {
        $json = StringUtil::jsonToArray('{"license": "MIT"}');
        $this->assertEquals('MIT', $json['license']);
    }

    /**
     * @test
     */
    public function it_throws_an_exception_when_converting_invalid_json()
    {
        $this->expectException(\RuntimeException::class);
        StringUtil::jsonToArray('/');
    }
}
