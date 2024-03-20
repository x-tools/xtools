<?php

declare(strict_types = 1);

namespace App\Tests\Helper;

use App\Helper\I18nHelper;
use App\Tests\SessionHelper;
use App\Tests\TestAdapter;
use DateTime;
use Krinkle\Intuition\Intuition;

/**
 * @covers \App\Helper\I18nHelper
 */
class I18nHelperTest extends TestAdapter
{
    use SessionHelper;

    protected I18nHelper $i18n;

    public function setUp(): void
    {
        $session = $this->createSession(static::createClient());
        $this->i18n = new I18nHelper(
            $this->getRequestStack($session),
            static::getContainer()->getParameter('kernel.project_dir')
        );
    }

    public function testGetters(): void
    {
        static::assertEquals(Intuition::class, get_class($this->i18n->getIntuition()));
        static::assertEquals('en', $this->i18n->getLang());
        static::assertEquals('English', $this->i18n->getLangName());
        static::assertGreaterThan(10, count($this->i18n->getAllLangs()));
    }

    public function testRTLAndFallbacks(): void
    {
        static::assertTrue($this->i18n->isRTL('ar'));
        static::assertEquals(['zh-hans', 'en'], array_values($this->i18n->getFallbacks('zh')));
    }

    public function testMessageHelpers(): void
    {
        static::assertEquals('Edit Counter', $this->i18n->msg('tool-editcounter'));
        static::assertTrue($this->i18n->msgExists('tool-editcounter'));
        static::assertEquals('foobar', $this->i18n->msgIfExists('foobar'));
    }

    public function testNumberFormatting(): void
    {
        static::assertEquals('1,234,567.89', $this->i18n->numberFormat(1234567.89132, 2));
        static::assertEquals('5%', $this->i18n->percentFormat(5));
        static::assertEquals('5.43%', $this->i18n->percentFormat(5.4321, null, 2));
        static::assertEquals('50%', $this->i18n->percentFormat(100, 200));
    }

    public function testDateFormat(): void
    {
        $datetime = '2023-01-23 12:34';
        static::assertEquals($datetime, $this->i18n->dateFormat('2023-01-23T12:34'));
        static::assertEquals($datetime, $this->i18n->dateFormat(new DateTime($datetime)));
        static::assertEquals($datetime, $this->i18n->dateFormat(1674477240));
    }
}
