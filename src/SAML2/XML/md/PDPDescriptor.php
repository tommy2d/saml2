<?php

declare(strict_types=1);

namespace SimpleSAML\SAML2\XML\md;

use DOMElement;
use SimpleSAML\Assert\Assert;
use SimpleSAML\SAML2\Constants;
use SimpleSAML\XML\Exception\InvalidDOMElementException;
use SimpleSAML\XML\Exception\TooManyElementsException;
use SimpleSAML\XML\Utils as XMLUtils;

use function preg_split;

/**
 * Class representing SAML 2 metadata PDPDescriptor.
 *
 * @package simplesamlphp/saml2
 */
final class PDPDescriptor extends AbstractRoleDescriptor
{
    /**
     * List of AuthzService endpoints.
     *
     * @var \SimpleSAML\SAML2\XML\md\AuthzService[]
     */
    protected array $authzServiceEndpoints = [];

    /**
     * List of AssertionIDRequestService endpoints.
     *
     * @var \SimpleSAML\SAML2\XML\md\AssertionIDRequestService[]
     */
    protected array $assertionIDRequestServiceEndpoints = [];

    /**
     * List of supported NameID formats.
     *
     * @var \SimpleSAML\SAML2\XML\md\NameIDFormat[]
     */
    protected array $NameIDFormats = [];


    /**
     * PDPDescriptor constructor.
     *
     * @param \SimpleSAML\SAML2\XML\md\AuthzService[] $authServiceEndpoints
     * @param string[] $protocolSupportEnumeration
     * @param \SimpleSAML\SAML2\XML\md\AssertionIDRequestService[] $assertionIDRequestService
     * @param \SimpleSAML\SAML2\XML\md\NameIDFormat[] $nameIDFormats
     * @param string|null $ID
     * @param int|null $validUntil
     * @param string|null $cacheDuration
     * @param \SimpleSAML\SAML2\XML\md\Extensions|null $extensions
     * @param string|null $errorURL
     * @param \SimpleSAML\SAML2\XML\md\Organization|null $organization
     * @param \SimpleSAML\SAML2\XML\md\KeyDescriptor[] $keyDescriptors
     * @param \SimpleSAML\SAML2\XML\md\ContactPerson[] $contacts
     */
    public function __construct(
        array $authServiceEndpoints,
        array $protocolSupportEnumeration,
        array $assertionIDRequestService = [],
        array $nameIDFormats = [],
        ?string $ID = null,
        ?int $validUntil = null,
        ?string $cacheDuration = null,
        ?Extensions $extensions = null,
        ?string $errorURL = null,
        ?Organization $organization = null,
        array $keyDescriptors = [],
        array $contacts = []
    ) {
        parent::__construct(
            $protocolSupportEnumeration,
            $ID,
            $validUntil,
            $cacheDuration,
            $extensions,
            $errorURL,
            $keyDescriptors,
            $organization,
            $contacts
        );
        $this->setAuthzServiceEndpoints($authServiceEndpoints);
        $this->setAssertionIDRequestServices($assertionIDRequestService);
        $this->setNameIDFormats($nameIDFormats);
    }


    /**
     * Initialize an IDPSSODescriptor from a given XML document.
     *
     * @param \DOMElement $xml The XML element we should load.
     * @return \SimpleSAML\SAML2\XML\md\PDPDescriptor
     *
     * @throws \SimpleSAML\XML\Exception\InvalidDOMElementException if the qualified name of the supplied element is wrong
     * @throws \SimpleSAML\XML\Exception\MissingAttributeException if the supplied element is missing one of the mandatory attributes
     * @throws \SimpleSAML\XML\Exception\TooManyElementsException if too many child-elements of a type are specified
     */
    public static function fromXML(DOMElement $xml): object
    {
        Assert::same($xml->localName, 'PDPDescriptor', InvalidDOMElementException::class);
        Assert::same($xml->namespaceURI, PDPDescriptor::NS, InvalidDOMElementException::class);

        $protocols = self::getAttribute($xml, 'protocolSupportEnumeration');
        $validUntil = self::getAttribute($xml, 'validUntil', null);
        $orgs = Organization::getChildrenOfClass($xml);
        Assert::maxCount($orgs, 1, 'More than one Organization found in this descriptor', TooManyElementsException::class);

        $extensions = Extensions::getChildrenOfClass($xml);
        Assert::maxCount($extensions, 1, 'Only one md:Extensions element is allowed.', TooManyElementsException::class);

        return new self(
            AuthzService::getChildrenOfClass($xml),
            preg_split('/[\s]+/', trim($protocols)),
            AssertionIDRequestService::getChildrenOfClass($xml),
            NameIDFormat::getChildrenOfClass($xml),
            self::getAttribute($xml, 'ID', null),
            $validUntil !== null ? XMLUtils::xsDateTimeToTimestamp($validUntil) : null,
            self::getAttribute($xml, 'cacheDuration', null),
            !empty($extensions) ? $extensions[0] : null,
            self::getAttribute($xml, 'errorURL', null),
            !empty($orgs) ? $orgs[0] : null,
            KeyDescriptor::getChildrenOfClass($xml),
            ContactPerson::getChildrenOfClass($xml)
        );
    }


    /**
     * Get the AuthzService endpoints of this PDPDescriptor
     *
     * @return \SimpleSAML\SAML2\XML\md\AuthzService[]
     */
    public function getAuthzServiceEndpoints(): array
    {
        return $this->authzServiceEndpoints;
    }


    /**
     * Set the AuthzService endpoints for this PDPDescriptor
     *
     * @param \SimpleSAML\SAML2\XML\md\AuthzService[] $authzServices
     * @throws \SimpleSAML\Assert\AssertionFailedException
     */
    protected function setAuthzServiceEndpoints(array $authzServices = []): void
    {
        Assert::minCount($authzServices, 1, 'At least one md:AuthzService endpoint must be present.');
        Assert::allIsInstanceOf(
            $authzServices,
            AuthzService::class,
            'All md:AuthzService endpoints must be an instance of AuthzService.'
        );
        $this->authzServiceEndpoints = $authzServices;
    }


    /**
     * Get the AssertionIDRequestService endpoints of this PDPDescriptor
     *
     * @return \SimpleSAML\SAML2\XML\md\AssertionIDRequestService[]
     */
    public function getAssertionIDRequestServices(): array
    {
        return $this->assertionIDRequestServiceEndpoints;
    }


    /**
     * Set the AssertionIDRequestService endpoints for this PDPDescriptor
     *
     * @param \SimpleSAML\SAML2\XML\md\AssertionIDRequestService[] $assertionIDRequestServices
     * @throws \SimpleSAML\Assert\AssertionFailedException
     */
    public function setAssertionIDRequestServices(array $assertionIDRequestServices): void
    {
        Assert::allIsInstanceOf(
            $assertionIDRequestServices,
            AssertionIDRequestService::class,
            'All md:AssertionIDRequestService endpoints must be an instance of AssertionIDRequestService.'
        );
        $this->assertionIDRequestServiceEndpoints = $assertionIDRequestServices;
    }


    /**
     * Get the NameIDFormats supported by this PDPDescriptor
     *
     * @return \SimpleSAML\SAML2\XML\md\NameIDFormat[]
     */
    public function getNameIDFormats(): array
    {
        return $this->NameIDFormats;
    }


    /**
     * Set the NameIDFormats supported by this PDPDescriptor
     *
     * @param \SimpleSAML\SAML2\XML\md\NameIDFormat[] $nameIDFormats
     */
    public function setNameIDFormats(array $nameIDFormats): void
    {
        Assert::allIsInstanceOf($nameIDFormats, NameIDFormat::class);
        $this->NameIDFormats = $nameIDFormats;
    }


    /**
     * Add this PDPDescriptor to an EntityDescriptor.
     *
     * @param \DOMElement $parent The EntityDescriptor we should append this IDPSSODescriptor to.
     * @return \DOMElement
     * @throws \Exception
     */
    public function toXML(DOMElement $parent = null): DOMElement
    {
        $e = parent::toXML($parent);

        foreach ($this->authzServiceEndpoints as $ep) {
            $ep->toXML($e);
        }

        foreach ($this->assertionIDRequestServiceEndpoints as $ep) {
            $ep->toXML($e);
        }

        foreach ($this->NameIDFormats as $nidFormat) {
            $nidFormat->toXML($e);
        }

        return $this->signElement($e);
    }
}
