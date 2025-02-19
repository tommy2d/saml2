<?php

declare(strict_types=1);

namespace SimpleSAML\Test\SAML2\XML\md;

use DOMDocument;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SimpleSAML\Assert\AssertionFailedException;
use SimpleSAML\SAML2\Constants;
use SimpleSAML\SAML2\XML\md\AbstractLocalizedName;
use SimpleSAML\SAML2\XML\mdui\PrivacyStatementURL;
use SimpleSAML\Test\XML\ArrayizableXMLTestTrait;
use SimpleSAML\Test\XML\SerializableXMLTestTrait;
use SimpleSAML\XML\DOMDocumentFactory;

use function dirname;
use function strval;

/**
 * Tests for localized names.
 *
 * @covers \SimpleSAML\SAML2\XML\mdui\PrivacyStatementURL
 * @covers \SimpleSAML\SAML2\XML\md\AbstractLocalizedURI
 * @covers \SimpleSAML\SAML2\XML\md\AbstractLocalizedName
 * @covers \SimpleSAML\SAML2\XML\md\AbstractMdElement
 * @package simplesamlphp/saml2
 */
final class PrivacyStatementURLTest extends TestCase
{
    use ArrayizableXMLTestTrait;
    use SerializableXMLTestTrait;


    /**
     */
    protected function setUp(): void
    {
        $this->testedClass = PrivacyStatementURL::class;

        $this->xmlRepresentation = DOMDocumentFactory::fromFile(
            dirname(dirname(dirname(dirname(__FILE__)))) . '/resources/xml/mdui_PrivacyStatementURL.xml'
        );

        $this->arrayRepresentation = ['en' => 'https://example.org/privacy'];
    }


    // test marshalling


    /**
     * Test creating a PrivacyStatementURL object from scratch.
     */
    public function testMarshalling(): void
    {
        $name = new PrivacyStatementURL('en', 'https://example.org/privacy');

        $this->assertEquals(
            $this->xmlRepresentation->saveXML($this->xmlRepresentation->documentElement),
            strval($name)
        );
    }


    // test unmarshalling


    /**
     * Test creating a PrivacyStatementURL from XML.
     */
    public function testUnmarshalling(): void
    {
        $name = PrivacyStatementURL::fromXML($this->xmlRepresentation->documentElement);
        $this->assertEquals(
            $this->xmlRepresentation->saveXML($this->xmlRepresentation->documentElement),
            strval($name)
        );
    }


    /**
     * Test that creating a PrivacyStatementURL with an invalid url throws an exception
     */
    public function testUnmarshallingFailsInvalidURL(): void
    {
        $document = $this->xmlRepresentation;
        $document->documentElement->textContent = 'this is no url';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('mdui:PrivacyStatementURL is not a valid URL.');
        PrivacyStatementURL::fromXML($document->documentElement);
    }
}
