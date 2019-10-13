<?php

declare(strict_types=1);

namespace JsonStreamingParser\Test;

use JsonStreamingParser\Listener\RegexListener;
use JsonStreamingParser\Parser;
use PHPUnit\Framework\TestCase;

error_reporting(E_ALL);
ini_set('display_errors', '1');

class RegexListenerTest extends TestCase
{
    /**
     * Test reading an attribute as well as a nested object
     * with a regex symbol.
     */
    public function testBasicRead(): void
    {
        $filename = __DIR__.'/data/example.json';
        $testJSON = json_decode(file_get_contents($filename), true);

        $tssnCall = $this->getMockBuilder(\stdClass::class)
            ->setMethods(['__invoke'])
            ->getMock()
        ;

        $tssnCall->expects($this->once())
            ->method('__invoke')
            ->with($testJSON[0]['totally sweet scientific notation'])
        ;

        $nested_objectCall = $this->getMockBuilder(\stdClass::class)
            ->setMethods(['__invoke'])
            ->getMock()
        ;

        $nested_objectCall->expects($this->once())
            ->method('__invoke')
            ->with($testJSON[1]['nested object']['nested object?'])
        ;

        $listener = new RegexListener(['/0/totally sweet scientific notation' => $tssnCall,
            "/1/nested object/nested object\?" => $nested_objectCall, ]);
        $parser = new Parser(fopen($filename, 'r'), $listener);
        $parser->parse();
    }

    /**
     * Test reading first and last elements in JSON.
     */
    public function testReadFirstAndLast(): void
    {
        $filename = __DIR__.'/data/plain.json';
        $testJSON = json_decode(file_get_contents($filename), true);

        $firstCall = $this->getMockBuilder(\stdClass::class)
            ->setMethods(['__invoke'])
            ->getMock()
        ;

        $firstCall->expects($this->once())
            ->method('__invoke')
            ->with($testJSON['key 1'])
        ;

        $lastCall = $this->getMockBuilder(\stdClass::class)
            ->setMethods(['__invoke'])
            ->getMock()
        ;

        $lastCall->expects($this->once())
            ->method('__invoke')
            ->with($testJSON['key 3'])
        ;

        $listener = new RegexListener(['/key 1' => $firstCall,
            '/key 3' => $lastCall, ]);
        $parser = new Parser(fopen($filename, 'r'), $listener);
        $parser->parse();
    }

    /**
     * Test reading a nested array of simple data types (integers).
     */
    public function testBasicReadArray(): void
    {
        $filename = __DIR__.'/data/example.json';
        $testJSON = json_decode(file_get_contents($filename), true);
        $arrayData = $testJSON[1]['nested object']['nested array'];

        $stream = fopen($filename, 'r');
        $this->assertNotNull($stream);

        $arrayCall = $this->getMockBuilder(\stdClass::class)
            ->setMethods(['__invoke'])
            ->getMock()
        ;

        $arrayList = array_map(function ($data) { return [$data]; },
                $arrayData);
        $arrayCall->expects($this->exactly(\count($arrayData)))
            ->method('__invoke')
            ->withConsecutive(...$arrayList)
        ;

        $listener = new RegexListener(["/1/nested object/nested array/\d*" => $arrayCall]);
        $parser = new Parser(fopen($filename, 'r'), $listener);
        $parser->parse();
    }

    /**
     * Test reading a nested array with objects.
     */
    public function testBasicReadArrayElement(): void
    {
        $filename = __DIR__.'/data/dateRanges.json';
        $testJSON = json_decode(file_get_contents($filename), true);
        $arrayData = array_column($testJSON['data'][0]['rows'], 'startDate');
        $arrayData2 = array_column($testJSON['data'][0]['rows'], 'endDate');
        $arrayData3 = $testJSON['anotherPlace'];

        $stream = fopen($filename, 'r');
        $this->assertNotNull($stream);

        $arrayCall = $this->getMockBuilder(\stdClass::class)
            ->setMethods(['__invoke'])
            ->getMock()
        ;

        $arrayList = array_map(function ($data) { return [$data]; },
            $arrayData);
        $arrayCall->expects($this->exactly(\count($arrayData)))
            ->method('__invoke')
            ->withConsecutive(...$arrayList)
        ;

        $arrayCall2 = $this->getMockBuilder(\stdClass::class)
            ->setMethods(['__invoke'])
            ->getMock()
        ;

        $arrayList2 = array_map(function ($data) { return [$data]; },
            $arrayData2);
        $arrayCall2->expects($this->exactly(\count($arrayData2)))
            ->method('__invoke')
            ->withConsecutive(...$arrayList2)
        ;

        $nested_objectCall = $this->getMockBuilder(\stdClass::class)
            ->setMethods(['__invoke'])
            ->getMock()
        ;

        $nested_objectCall->expects($this->once())
            ->method('__invoke')
            ->with($arrayData3)
        ;

        $listener = new RegexListener(["/data/0/rows/\d*/startDate" => $arrayCall,
            "/data/0/rows/\d*/endDate" => $arrayCall2,
            '/anotherPlace' => $nested_objectCall,
        ]);
        $parser = new Parser(fopen($filename, 'r'), $listener);
        $parser->parse();
    }

    /**
     * Test reading root level nested objects.
     */
    public function testBasicReadArrayObjects(): void
    {
        $filename = __DIR__.'/data/example.json';
        $testJSON = json_decode(file_get_contents($filename), true);

        $arrayCall = $this->getMockBuilder(\stdClass::class)
            ->setMethods(['__invoke'])
            ->getMock()
        ;

        $arrayList = array_map(function ($data) { return [$data]; },
                 $testJSON);
        $arrayCall->expects($this->exactly(\count($testJSON)))
            ->method('__invoke')
            ->withConsecutive(...$arrayList)
        ;

        $listener = new RegexListener(["/\d*" => $arrayCall]);
        $parser = new Parser(fopen($filename, 'r'), $listener);
        $parser->parse();
    }

    /**
     * Test large file list of ID's.
     */
    public function testBasicReadArrayIDs(): void
    {
        $filename = __DIR__.'/data/ratherBig.json';
        $testJSON = json_decode(file_get_contents($filename), true);

        $arrayCall = $this->getMockBuilder(\stdClass::class)
            ->setMethods(['__invoke'])
            ->getMock()
        ;

        $arrayList = array_map(function ($data) { return [$data]; },
            array_column($testJSON['rows'], 'id'));
        $arrayCall->expects($this->exactly(\count($testJSON['rows'])))
            ->method('__invoke')
            ->withConsecutive(...$arrayList)
        ;

        $listener = new RegexListener(["/rows/\d*/id" => $arrayCall]);
        $parser = new Parser(fopen($filename, 'r'), $listener);
        $parser->parse();
    }

    /**
     * Testing the capture group in the JSON path is passed to callback.
     * As the example file has an 'endDate' element in various elements,
     * the path of (.*)/endDate should result in both the element
     * value as well as the base element name being passed into the callback.
     */
    public function testCaptureGroup(): void
    {
        $filename = __DIR__.'/data/dateRanges.json';

        $arrayCall = $this->getMockBuilder(\stdClass::class)
            ->setMethods(['__invoke'])
            ->getMock()
        ;

        $arrayCall->expects($this->exactly(3))
            ->method('__invoke')
            ->withConsecutive(['2013-10-25', '/data/0/rows/0'],
                ['2013-10-27', '/data/0/rows/1'],
                ['2013-11-10', '/anotherPlace'])
        ;

        $listener = new RegexListener(['(.*)/endDate' => $arrayCall]);
        $parser = new Parser(fopen($filename, 'r'), $listener);
        $parser->parse();
    }

    /**
     * Testing ability to stop load.
     */
    public function testBasicReadStop(): void
    {
        $filename = __DIR__.'/data/example.json';
        $testJSON = json_decode(file_get_contents($filename), true);

        $arrayCall = $this->getMockBuilder(\stdClass::class)
            ->setMethods(['__invoke'])
            ->getMock()
        ;

        $arrayCall->expects($this->once())
            ->method('__invoke')
            ->with($testJSON[0]['name'])
        ;

        $notCall = $this->getMockBuilder(\stdClass::class)
            ->setMethods(['__invoke'])
            ->getMock()
        ;

        $notCall->expects($this->never())
            ->method('__invoke')
            ->with($testJSON[0]['zero character'])
        ;

        $listener = new RegexListener();
        $parser = new Parser(fopen($filename, 'r'), $listener);
        $listener->setMatch(['/0/name' => $arrayCall,
            '/0/integer' => function ($data) use ($parser): void {
                $parser->stop();
            },
            '/0/zero character' => $notCall,
        ]);
        $parser->parse();
    }

    /**
     * Test reading a corrupt file.
     * Extracts the type element of each of the features.
     */
    public function testCorruptFile(): void
    {
        $filename = __DIR__.'/data/example.corrupted.json';

        $arrayCall = $this->getMockBuilder(\stdClass::class)
            ->setMethods(['__invoke'])
            ->getMock()
        ;

        $arrayCall->expects($this->exactly(2))
            ->method('__invoke')
            ->withConsecutive(['Point'], ['Polygon'])
        ;

        $listener = new RegexListener(["/features/\d*/geometry/type" => $arrayCall]);
        $parser = new Parser(fopen($filename, 'r'), $listener);
        $parser->parse();
    }

    /**
     * Test reading a simple array of integers.
     */
    public function testBasicArrayRead(): void
    {
        $filename = __DIR__.'/data/simpleArray.json';
        $testJSON = json_decode(file_get_contents($filename), true);

        $value1Call = $this->getMockBuilder(\stdClass::class)
            ->setMethods(['__invoke'])
            ->getMock()
        ;

        $value1Call->expects($this->once())
            ->method('__invoke')
            ->with($testJSON[0])
        ;

        $value2Call = $this->getMockBuilder(\stdClass::class)
            ->setMethods(['__invoke'])
            ->getMock()
        ;

        $value2Call->expects($this->once())
            ->method('__invoke')
            ->with($testJSON[1])
        ;

        $listener = new RegexListener(['/0' => $value1Call,
            '/1' => $value2Call, ]);
        $parser = new Parser(fopen($filename, 'r'), $listener);
        $parser->parse();
    }

    /**
     * Test reading a simple array of integers with capture group.
     */
    public function testBasicArrayReadCapture(): void
    {
        $filename = __DIR__.'/data/simpleArray.json';

        $valueCall = $this->getMockBuilder(\stdClass::class)
            ->setMethods(['__invoke'])
            ->getMock()
        ;

        $valueCall->expects($this->exactly(2))
            ->method('__invoke')
            ->withConsecutive(['1', '/0'],
                ['2', '/1'])
        ;

        $listener = new RegexListener(["(/\d*)" => $valueCall]);
        $parser = new Parser(fopen($filename, 'r'), $listener);
        $parser->parse();
    }
}
