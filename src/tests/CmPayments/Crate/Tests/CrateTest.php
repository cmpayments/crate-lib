<?php

namespace CmPayments\Crate\Tests;

use ArrayIterator;
use FilesystemIterator;
use CmPayments\Crate\Crate;
use CmPayments\Crate\Compactor\Php;
use CmPayments\Crate\StubGenerator;
use Herrera\PHPUnit\TestCase;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamWrapper;
use Phar;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class CrateTest extends TestCase
{
    /**
     * @var Crate
     */
    private $crate;

    /**
     * @var string
     */
    private $cwd;

    /**
     * @var Phar
     */
    private $phar;

    public function getPrivateKey()
    {
        return array(
            <<<KEY
-----BEGIN RSA PRIVATE KEY-----
Proc-Type: 4,ENCRYPTED
DEK-Info: DES-EDE3-CBC,3FF97F75E5A8F534

TvEPC5L3OXjy4X5t6SRsW6J4Dfdgw0Mfjqwa4OOI88uk5L8SIezs4sHDYHba9GkG
RKVnRhA5F+gEHrabsQiVJdWPdS8xKUgpkvHqoAT8Zl5sAy/3e/EKZ+Bd2pS/t5yQ
aGGqliG4oWecx42QGL8rmyrbs2wnuBZmwQ6iIVIfYabwpiH+lcEmEoxomXjt9A3j
Sh8IhaDzMLnVS8egk1QvvhFjyXyBIW5mLIue6cdEgINbxzRReNQgjlyHS8BJRLp9
EvJcZDKJiNJt+VLncbfm4ZhbdKvSsbZbXC/Pqv06YNMY1+m9QwszHJexqjm7AyzB
MkBFedcxcxqvSb8DaGgQfUkm9rAmbmu+l1Dncd72Cjjf8fIfuodUmKsdfYds3h+n
Ss7K4YiiNp7u9pqJBMvUdtrVoSsNAo6i7uFa7JQTXec9sbFN1nezgq1FZmcfJYUZ
rdpc2J1hbHTfUZWtLZebA72GU63Y9zkZzbP3SjFUSWniEEbzWbPy2sAycHrpagND
itOQNHwZ2Me81MQQB55JOKblKkSha6cNo9nJjd8rpyo/lc/Iay9qlUyba7RO0V/t
wm9ZeUZL+D2/JQH7zGyLxkKqcMC+CFrNYnVh0U4nk3ftZsM+jcyfl7ScVFTKmcRc
ypcpLwfS6gyenTqiTiJx/Zca4xmRNA+Fy1EhkymxP3ku0kTU6qutT2tuYOjtz/rW
k6oIhMcpsXFdB3N9iHT4qqElo3rVW/qLQaNIqxd8+JmE5GkHmF43PhK3HX1PCmRC
TnvzVS0y1l8zCsRToUtv5rCBC+r8Q3gnvGGnT4jrsp98ithGIQCbbQ==
-----END RSA PRIVATE KEY-----
KEY
            ,
            'test'
        );
    }

    public function testAddCompactor()
    {
        $compactor = new Compactor();

        $this->crate->addCompactor($compactor);

        $this->assertTrue(
            $this->getPropertyValue($this->crate, 'compactors')
                 ->contains($compactor)
        );
    }

    public function testAddFile()
    {
        $file = $this->createFile();

        file_put_contents($file, 'test');

        $this->crate->addFile($file, 'test/test.php');

        $this->assertEquals(
            'test',
            file_get_contents('phar://test.phar/test/test.php')
        );
    }

    public function testAddFileNotExist()
    {
        $this->setExpectedException(
            'CmPayments\\Crate\\Exception\\FileException',
            'The file "/does/not/exist" does not exist or is not a file.'
        );

        $this->crate->addFile('/does/not/exist');
    }

    public function testAddFileReadError()
    {
        vfsStreamWrapper::setRoot($root = vfsStream::newDirectory('test'));

        $root->addChild(vfsStream::newFile('test.php', 0000));

        $this->setExpectedException(
            'CmPayments\\Crate\\Exception\\FileException',
            'failed to open stream'
        );

        $this->crate->addFile('vfs://test/test.php');
    }

    public function testAddFromString()
    {
        $original = <<<SOURCE
<?php

/**
 * My class.
 */
class @thing@
{
    /**
     * My method.
     */
    public function @other_thing@()
    {
    }
}
SOURCE;

        $expected = <<<SOURCE
<?php




class MyClass
{



public function myMethod()
{
}
}
SOURCE;

        $this->crate->addCompactor(new Php());
        $this->crate->setValues(
            array(
                '@thing@' => 'MyClass',
                '@other_thing@' => 'myMethod'
            )
        );

        $this->crate->addFromString('test/test.php', $original);

        $this->assertEquals(
            $expected,
            file_get_contents('phar://test.phar/test/test.php')
        );
    }

    public function testBuildFromDirectory()
    {
        mkdir('test/sub', 0755, true);
        touch('test/sub.txt');

        file_put_contents(
            'test/sub/test.php',
            '<?php echo "Hello, @name@!\n";'
        );

        $this->crate->setValues(array('@name@' => 'world'));
        $this->crate->buildFromDirectory($this->cwd, '/\.php$/');

        $this->assertFalse(isset($this->phar['test/sub.txt']));
        $this->assertEquals(
            '<?php echo "Hello, world!\n";',
            file_get_contents('phar://test.phar/test/sub/test.php')
        );
    }

    public function testBuildFromIterator()
    {
        mkdir('test/sub', 0755, true);

        file_put_contents(
            'test/sub/test.php',
            '<?php echo "Hello, @name@!\n";'
        );

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $this->cwd,
                FilesystemIterator::KEY_AS_PATHNAME
                | FilesystemIterator::CURRENT_AS_FILEINFO
                | FilesystemIterator::SKIP_DOTS
            )
        );

        $this->crate->setValues(array('@name@' => 'world'));
        $this->crate->buildFromIterator($iterator, $this->cwd);

        $this->assertEquals(
            '<?php echo "Hello, world!\n";',
            file_get_contents('phar://test.phar/test/sub/test.php')
        );
    }

    /**
     * @depends testBuildFromIterator
     */
    public function testBuildFromIteratorMixed()
    {
        mkdir('object');
        mkdir('string');

        touch('object.php');
        touch('string.php');

        $this->crate->buildFromIterator(
            new ArrayIterator(
                array(
                    'object' => new SplFileInfo($this->cwd . '/object'),
                    'string' => $this->cwd . '/string',
                    'object.php' => new SplFileInfo($this->cwd . '/object.php'),
                    'string.php' => $this->cwd . '/string.php',
                )
            ),
            $this->cwd
        );

        /** @var $phar SplFileInfo[] */
        $phar = $this->phar;

        $this->assertTrue($phar['object']->isDir());
        $this->assertTrue($phar['string']->isDir());
        $this->assertTrue($phar['object.php']->isFile());
        $this->assertTrue($phar['string.php']->isFile());
    }

    public function testBuildFromIteratorBaseRequired()
    {
        $this->setExpectedException(
            'CmPayments\\Crate\\Exception\\InvalidArgumentException',
            'The $base argument is required for SplFileInfo values.'
        );

        $this->crate->buildFromIterator(
            new ArrayIterator(array(new SplFileInfo($this->cwd)))
        );
    }

    public function testBuildFromIteratorOutsideBase()
    {
        $this->setExpectedException(
            'CmPayments\\Crate\\Exception\\UnexpectedValueException',
            "The file \"{$this->cwd}\" is not in the base directory."
        );

        $this->crate->buildFromIterator(
            new ArrayIterator(array(new SplFileInfo($this->cwd))),
            __DIR__
        );
    }

    public function testBuildFromIteratorInvalidKey()
    {
        $this->setExpectedException(
            'CmPayments\\Crate\\Exception\\UnexpectedValueException',
            'The key returned by the iterator (integer) is not a string.'
        );

        $this->crate->buildFromIterator(new ArrayIterator(array('test')));
    }

    public function testBuildFromIteratorInvalid()
    {
        $this->setExpectedException(
            'CmPayments\\Crate\\Exception\\UnexpectedValueException',
            'The iterator value "resource" was not expected.'
        );

        $this->crate->buildFromIterator(
            new ArrayIterator(array('stream' => STDOUT))
        );
    }

    /**
     * @depends testAddCompactor
     */
    public function testCompactContents()
    {
        $compactor = new Compactor();

        $this->crate->addCompactor($compactor);

        $this->assertEquals(
            'my value',
            $this->crate->compactContents('test.php', ' my value ')
        );
    }

    public function testCreate()
    {
        $crate = Crate::create('test2.phar');

        $this->assertInstanceOf('CmPayments\\Crate\\Crate', $crate);
        $this->assertEquals(
            'test2.phar',
            $this->getPropertyValue($crate, 'file')
        );
    }

    public function testGetPhar()
    {
        $this->assertSame($this->phar, $this->crate->getPhar());
    }

    public function testGetSignature()
    {
        $path = RES_DIR . '/example.phar';
        $phar = new Phar($path);

        $this->assertEquals(
            $phar->getSignature(),
            Crate::getSignature($path)
        );
    }

    public function testReplaceValues()
    {
        $this->setPropertyValue(
            $this->crate,
            'values',
            array(
                '@1@' => 'a',
                '@2@' => 'b'
            )
        );

        $this->assertEquals('ab@3@', $this->crate->replaceValues('@1@@2@@3@'));
    }

    public function testSetStubUsingFileNotExist()
    {
        $this->setExpectedException(
            'CmPayments\\Crate\\Exception\\FileException',
            'The file "/does/not/exist" does not exist or is not a file.'
        );

        $this->crate->setStubUsingFile('/does/not/exist');
    }

    public function testSetStubUsingFileReadError()
    {
        vfsStreamWrapper::setRoot($root = vfsStream::newDirectory('test'));

        $root->addChild(vfsStream::newFile('test.php', 0000));

        $this->setExpectedException(
            'CmPayments\\Crate\\Exception\\FileException',
            'failed to open stream'
        );

        $this->crate->setStubUsingFile('vfs://test/test.php');
    }

    public function testSetStubUsingFile()
    {
        $file = $this->createFile();

        file_put_contents(
            $file,
            <<<STUB
#!/usr/bin/env php
<?php
echo "@replace_me@";
__HALT_COMPILER();
STUB
        );

        $this->crate->setValues(array('@replace_me@' => 'replaced'));
        $this->crate->setStubUsingFile($file, true);

        $this->assertEquals(
            'replaced',
            exec('php test.phar')
        );
    }

    public function testSetValues()
    {
        $rand = rand();

        $this->crate->setValues(array('@rand@' => $rand));

        $this->assertEquals(
            array('@rand@' => $rand),
            $this->getPropertyValue($this->crate, 'values')
        );
    }

    public function testSetValuesNonScalar()
    {
        $this->setExpectedException(
            'CmPayments\\Crate\\Exception\\InvalidArgumentException',
            'Non-scalar values (such as resource) are not supported.'
        );

        $this->crate->setValues(array('stream' => STDOUT));
    }

    /**
     * @depends testGetPhar
     */
    public function testSign()
    {
        if (false === extension_loaded('openssl')) {
            $this->markTestSkipped('The "openssl" extension is not available.');
        }

        list($key, $password) = $this->getPrivateKey();

        $this->crate->getPhar()->addFromString(
            'test.php',
            '<?php echo "Hello, world!\n";'
        );

        $this->crate->getPhar()->setStub(
            StubGenerator::create()
                ->index('test.php')
                ->generate()
        );

        $this->crate->sign($key, $password);

        $this->assertEquals(
            'Hello, world!',
            exec('php test.phar')
        );
    }

    /**
     * @depends testSign
     */
    public function testSignWriteError()
    {
        list($key, $password) = $this->getPrivateKey();

        mkdir('test.phar.pubkey');

        $this->crate->getPhar()->addFromString('test.php', '<?php $test = 1;');

        $this->setExpectedException(
            'CmPayments\\Crate\\Exception\\FileException',
            'failed to open stream'
        );

        $this->crate->sign($key, $password);
    }

    /**
     * @depends testSign
     */
    public function testSignUsingFile()
    {
        if (false === extension_loaded('openssl')) {
            $this->markTestSkipped('The "openssl" extension is not available.');
        }

        list($key, $password) = $this->getPrivateKey();

        $file = $this->createFile();

        file_put_contents($file, $key);

        $this->crate->getPhar()->addFromString(
            'test.php',
            '<?php echo "Hello, world!\n";'
        );

        $this->crate->getPhar()->setStub(
            StubGenerator::create()
                ->index('test.php')
                ->generate()
        );

        $this->crate->signUsingFile($file, $password);

        $this->assertEquals(
            'Hello, world!',
            exec('php test.phar')
        );
    }

    public function testSignUsingFileNotExist()
    {
        $this->setExpectedException(
            'CmPayments\\Crate\\Exception\\FileException',
            'The file "/does/not/exist" does not exist or is not a file.'
        );

        $this->crate->signUsingFile('/does/not/exist');
    }

    public function testSignUsingFileReadError()
    {
        $root = vfsStream::newDirectory('test');
        $root->addChild(vfsStream::newFile('private.key', 0000));

        vfsStreamWrapper::setRoot($root);

        $this->setExpectedException(
            'CmPayments\\Crate\\Exception\\FileException',
            'failed to open stream'
        );

        $this->crate->signUsingFile('vfs://test/private.key');
    }

    protected function tearDown()
    {
        unset($this->crate, $this->phar);

        parent::tearDown();
    }

    protected function setUp()
    {
        chdir($this->cwd = $this->createDir());

        $this->phar = new Phar('test.phar');
        $this->crate = new Crate($this->phar, 'test.phar');
    }
}
