<?php

declare(strict_types=1);

namespace SAML2\XML\samlp;

use DOMDocument;
use DOMElement;
use Exception;
use RobRichards\XMLSecLibs\XMLSecEnc;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use SAML2\Constants;
use SAML2\Exception\InvalidArgumentException;
use SAML2\XML\ds\Signature;
use SAML2\XML\saml\Issuer;
use SAML2\XML\saml\NameID;
use SAML2\XML\saml\Subject;
use SAML2\XML\saml\SubjectConfirmation;
use SAML2\Utils;
use Webmozart\Assert\Assert;

/**
 * Class for SAML 2 authentication request messages.
 *
 * @package SimpleSAMLphp
 */
class AuthnRequest extends AbstractRequest
{
    /**
     * @var \SAML2\XML\saml\Subject|null
     */
    protected $subject = null;

    /**
     * The options for what type of name identifier should be returned.
     *
     * @var \SAML2\XML\samlp\NameIDPolicy|null
     */
    protected $nameIdPolicy = null;

    /**
     * Whether the Identity Provider must authenticate the user again.
     *
     * @var bool|null
     */
    protected $forceAuthn = false;

    /**
     * Optional ProviderID attribute
     *
     * @var string|null
     */
    protected $ProviderName = null;

    /**
     * Set to true if this request is passive.
     *
     * @var bool|null
     */
    protected $isPassive = false;

    /**
     * The list of providerIDs in this request's scoping element
     *
     * @var array
     */
    protected $IDPList = [];

    /**
     * The ProxyCount in this request's scoping element
     *
     * @var int|null
     */
    protected $ProxyCount = null;

    /**
     * The RequesterID list in this request's scoping element
     *
     * @var array
     */
    protected $RequesterID = [];

    /**
     * The URL of the assertion consumer service where the response should be delivered.
     *
     * @var string|null
     */
    protected $assertionConsumerServiceURL;

    /**
     * What binding should be used when sending the response.
     *
     * @var string|null
     */
    protected $protocolBinding;

    /**
     * The index of the AttributeConsumingService.
     *
     * @var int|null
     */
    protected $attributeConsumingServiceIndex;

    /**
     * The index of the AssertionConsumerService.
     *
     * @var int|null
     */
    protected $assertionConsumerServiceIndex;

    /**
     * What authentication context was requested.
     *
     * @var \SAML2\XML\samlp\RequestedAuthnContext|null
     */
    protected $requestedAuthnContext;

    /**
     * Audiences to send in the request.
     *
     * @var array
     */
    protected $audiences = [];

    /**
     * @var \SAML2\XML\saml\SubjectConfirmation[]
     */
    protected $subjectConfirmation = [];


    /**
     * Constructor for SAML 2 AuthnRequest
     *
     * @param \SAML2\XML\samlp\RequestedAuthnContext $requestedAuthnContext
     * @param \SAML2\XML\saml\Subject $subject
     * @param bool $forceAuthn
     * @param bool $isPassive
     * @param string $assertionConsumerServiceUrl
     * @param string $protocolBinding
     * @param int $attributeConsumingServiceIndex
     * @param string $providerName

     * @param \SAML2\XML\saml\Issuer|null $issuer
     * @param string|null $id
     * @param string|null $version
     * @param int|null $issueInstant
     * @param string|null $destination
     * @param string|null $consent
     * @param \SAML2\XML\samlp\Extensions $extensions
     * @param string|null $relayState
     */
    public function __construct(
        ?RequestedAuthnContext $requestedAuthnContext = null,
        ?Subject $subject = null,
        ?bool $forceAuthn = null,
        ?bool $isPassive = null,
        ?string $assertionConsumerServiceUrl = null,
        ?string $protocolBinding = null,
        ?int $attributeConsumingServiceIndex = null,
        ?string $providerName = null,

        ?Issuer $issuer = null,
        ?string $id = null,
        ?string $version = null,
        ?int $issueInstant = null,
        ?string $destination = null,
        ?string $consent = null,
        ?Extensions $extensions = null,
        ?string $relayState = null
    ) {
        parent::__construct($issuer, $id, $version, $issueInstant, $destination, $consent, $extensions, $relayState);

        $this->setRequestedAuthnContext($requestedAuthnContext);
        $this->setSubject($subject);
        $this->setForceAuthn($forceAuthn);
        $this->setIsPassive($isPassive);
        $this->setAssertionConsumerServiceUrl($assertionConsumerServiceUrl);
        $this->setProtocolBinding($protocolBinding);
        $this->setAttributeConsumingServiceIndex($attributeConsumingServiceIndex);
        $this->setProviderName($providerName);
    }


    /**
     * @param $xml
     * @throws \Exception
     * @return void
     */
    private function parseSubject(DOMElement $xml): void
    {
        $this->subject = array_pop(Subject::getChildrenOfClass($xml));
    }


    /**
     * @param \DOMElement $xml
     * @throws \Exception
     * @return void
     */
    protected function parseNameIdPolicy(DOMElement $xml): void
    {
        /** @var \DOMElement[] $nameIdPolicy */
        $nameIdPolicy = Utils::xpQuery($xml, './saml_protocol:NameIDPolicy');
        if (empty($nameIdPolicy)) {
            return;
        }

        $this->nameIdPolicy = NameIDPolicy::fromXML($nameIdPolicy[0]);
    }


    /**
     * @param \DOMElement $xml
     * @return void
     */
    protected function parseRequestedAuthnContext(DOMElement $xml): void
    {
        /** @var \DOMElement[] $requestedAuthnContext */
        $requestedAuthnContext = Utils::xpQuery($xml, './saml_protocol:RequestedAuthnContext');
        if (empty($requestedAuthnContext)) {
            return;
        }

        $this->requestedAuthnContext = RequestedAuthnContext::fromXML($requestedAuthnContext[0]);
    }


    /**
     * @param \DOMElement $xml
     * @throws \Exception
     * @return void
     */
    protected function parseScoping(DOMElement $xml): void
    {
        /** @var \DOMElement[] $scoping */
        $scoping = Utils::xpQuery($xml, './saml_protocol:Scoping');
        if (empty($scoping)) {
            return;
        }

        $scoping = $scoping[0];

        if ($scoping->hasAttribute('ProxyCount')) {
            $this->ProxyCount = (int) $scoping->getAttribute('ProxyCount');
        }
        /** @var \DOMElement[] $idpEntries */
        $idpEntries = Utils::xpQuery($scoping, './saml_protocol:IDPList/saml_protocol:IDPEntry');

        foreach ($idpEntries as $idpEntry) {
            if (!$idpEntry->hasAttribute('ProviderID')) {
                throw new Exception("Could not get ProviderID from Scoping/IDPEntry element in AuthnRequest object");
            }
            $this->IDPList[] = $idpEntry->getAttribute('ProviderID');
        }

        /** @var \DOMElement[] $requesterIDs */
        $requesterIDs = Utils::xpQuery($scoping, './saml_protocol:RequesterID');
        foreach ($requesterIDs as $requesterID) {
            $this->RequesterID[] = trim($requesterID->textContent);
        }
    }


    /**
     * @param \DOMElement $xml
     * @return void
     */
    protected function parseConditions(DOMElement $xml): void
    {
        /** @var \DOMElement[] $conditions */
        $conditions = Utils::xpQuery($xml, './saml_assertion:Conditions');
        if (empty($conditions)) {
            return;
        }
        $conditions = $conditions[0];

        /** @var \DOMElement[] $ar */
        $ar = Utils::xpQuery($conditions, './saml_assertion:AudienceRestriction');
        if (empty($ar)) {
            return;
        }
        $ar = $ar[0];

        /** @var \DOMElement[] $audiences */
        $audiences = Utils::xpQuery($ar, './saml_assertion:Audience');
        $this->audiences = array();
        foreach ($audiences as $a) {
            $this->audiences[] = trim($a->textContent);
        }
    }


    /**
     * Retrieve the Subject.
     *
     * @return \SAML2\XML\saml\Subject|null The Subject.
     */
    public function getSubject(): ?Subject
    {
        return $this->subject;
    }


    /**
     * Set the Subject.
     *
     * @param \SAML2\XML\saml\Subject|null $subject The Subject.
     * @return void
     */
    private function setSubject(?Subject $subject): void
    {
        $this->subect = $subject;
    }


    /**
     * Retrieve the NameIdPolicy.
     *
     * @see \SAML2\AuthnRequest::setNameIdPolicy()
     * @return \SAML2\XML\samlp\NameIDPolicy|null The NameIdPolicy.
     */
    public function getNameIdPolicy(): ?NameIDPolicy
    {
        return $this->nameIdPolicy;
    }


    /**
     * Set the NameIDPolicy.
     *
     * @param \SAML2\XML\samlp\NameIDPolicy|null $nameIdPolicy The NameIDPolicy.
     * @return void
     */
    private function setNameIdPolicy(?NameIDPolicy $nameIdPolicy): void
    {
        $this->nameIdPolicy = $nameIdPolicy;
    }


    /**
     * Retrieve the value of the ForceAuthn attribute.
     *
     * @return bool|null The ForceAuthn attribute.
     */
    public function getForceAuthn(): ?bool
    {
        return $this->forceAuthn;
    }


    /**
     * Set the value of the ForceAuthn attribute.
     *
     * @param bool $forceAuthn The ForceAuthn attribute.
     * @return void
     */
    private function setForceAuthn(?bool $forceAuthn): void
    {
        $this->forceAuthn = $forceAuthn;
    }


    /**
     * Retrieve the value of the ProviderName attribute.
     *
     * @return string|null The ProviderName attribute.
     */
    public function getProviderName(): ?string
    {
        return $this->ProviderName;
    }


    /**
     * Set the value of the ProviderName attribute.
     *
     * @param string|null $ProviderName The ProviderName attribute.
     * @return void
     */
    private function setProviderName(?string $ProviderName): void
    {
        $this->ProviderName = $ProviderName;
    }


    /**
     * Retrieve the value of the IsPassive attribute.
     *
     * @return bool|null The IsPassive attribute.
     */
    public function getIsPassive(): ?bool
    {
        return $this->isPassive;
    }


    /**
     * Set the value of the IsPassive attribute.
     *
     * @param bool|null $isPassive The IsPassive attribute.
     * @return void
     */
    private function setIsPassive(?bool $isPassive): void
    {
        $this->isPassive = $isPassive;
    }


    /**
     * Retrieve the audiences from the request.
     * This may be an empty string, in which case no audience is included.
     *
     * @return array The audiences.
     */
    public function getAudiences(): array
    {
        return $this->audiences;
    }


    /**
     * Set the audiences to send in the request.
     * This may be an empty string, in which case no audience will be sent.
     *
     * @param array $audiences The audiences.
     * @return void
     */
    private function setAudiences(array $audiences): void
    {
        $this->audiences = $audiences;
    }


    /**
     * This function sets the scoping for the request.
     * See Core 3.4.1.2 for the definition of scoping.
     * Currently we support an IDPList of idpEntries.
     *
     * Each idpEntries consists of an array, containing
     * keys (mapped to attributes) and corresponding values.
     * Allowed attributes: Loc, Name, ProviderID.
     *
     * For backward compatibility, an idpEntries can also
     * be a string instead of an array, where each string
     * is mapped to the value of attribute ProviderID.
     *
     * @param array $IDPList List of idpEntries to scope the request to.
     * @return void
     */
    private function setIDPList(array $IDPList): void
    {
        $this->IDPList = $IDPList;
    }


    /**
     * This function retrieves the list of providerIDs from this authentication request.
     * Currently we only support a list of ipd ientity id's.
     *
     * @return array List of idp EntityIDs from the request
     */
    public function getIDPList(): array
    {
        return $this->IDPList;
    }


    /**
     * @param int $ProxyCount
     * @return void
     */
    private function setProxyCount(int $ProxyCount): void
    {
        $this->ProxyCount = $ProxyCount;
    }


    /**
     * @return int|null
     */
    public function getProxyCount(): ?int
    {
        return $this->ProxyCount;
    }


    /**
     * @param array $RequesterID
     * @return void
     */
    private function setRequesterID(array $RequesterID): void
    {
        $this->RequesterID = $RequesterID;
    }


    /**
     * @return array
     */
    public function getRequesterID(): array
    {
        return $this->RequesterID;
    }


    /**
     * Retrieve the value of the AssertionConsumerServiceURL attribute.
     *
     * @return string|null The AssertionConsumerServiceURL attribute.
     */
    public function getAssertionConsumerServiceURL(): ?string
    {
        return $this->assertionConsumerServiceURL;
    }


    /**
     * Set the value of the AssertionConsumerServiceURL attribute.
     *
     * @param string|null $assertionConsumerServiceURL The AssertionConsumerServiceURL attribute.
     * @return void
     */
    private function setAssertionConsumerServiceURL(string $assertionConsumerServiceURL = null): void
    {
        $this->assertionConsumerServiceURL = $assertionConsumerServiceURL;
    }


    /**
     * Retrieve the value of the ProtocolBinding attribute.
     *
     * @return string|null The ProtocolBinding attribute.
     */
    public function getProtocolBinding(): ?string
    {
        return $this->protocolBinding;
    }


    /**
     * Set the value of the ProtocolBinding attribute.
     *
     * @param string $protocolBinding The ProtocolBinding attribute.
     * @return void
     */
    private function setProtocolBinding(string $protocolBinding = null): void
    {
        $this->protocolBinding = $protocolBinding;
    }


    /**
     * Retrieve the value of the AttributeConsumingServiceIndex attribute.
     *
     * @return int|null The AttributeConsumingServiceIndex attribute.
     */
    public function getAttributeConsumingServiceIndex(): ?int
    {
        return $this->attributeConsumingServiceIndex;
    }


    /**
     * Set the value of the AttributeConsumingServiceIndex attribute.
     *
     * @param int|null $attributeConsumingServiceIndex The AttributeConsumingServiceIndex attribute.
     * @return void
     */
    private function setAttributeConsumingServiceIndex(int $attributeConsumingServiceIndex = null): void
    {
        $this->attributeConsumingServiceIndex = $attributeConsumingServiceIndex;
    }


    /**
     * Retrieve the value of the AssertionConsumerServiceIndex attribute.
     *
     * @return int|null The AssertionConsumerServiceIndex attribute.
     */
    public function getAssertionConsumerServiceIndex(): ?int
    {
        return $this->assertionConsumerServiceIndex;
    }


    /**
     * Set the value of the AssertionConsumerServiceIndex attribute.
     *
     * @param int|null $assertionConsumerServiceIndex The AssertionConsumerServiceIndex attribute.
     * @return void
     */
    private function setAssertionConsumerServiceIndex(int $assertionConsumerServiceIndex = null): void
    {
        $this->assertionConsumerServiceIndex = $assertionConsumerServiceIndex;
    }


    /**
     * Retrieve the RequestedAuthnContext.
     *
     * @return \SAML2\XML\samlp\RequestedAuthnContext|null The RequestedAuthnContext.
     */
    public function getRequestedAuthnContext(): ?RequestedAuthnContext
    {
        return $this->requestedAuthnContext;
    }


    /**
     * Set the RequestedAuthnContext.
     *
     * @param \SAML2\XML\samlp\RequestedAuthnContext|null $requestedAuthnContext The RequestedAuthnContext.
     * @return void
     */
    private function setRequestedAuthnContext(RequestedAuthnContext $requestedAuthnContext = null): void
    {
        $this->requestedAuthnContext = $requestedAuthnContext;
    }


    /**
     * Retrieve the SubjectConfirmation elements we have in our Subject element.
     *
     * @return \SAML2\XML\saml\SubjectConfirmation[]
     */
    public function getSubjectConfirmation(): array
    {
        return $this->subjectConfirmation;
    }


    /**
     * Set the SubjectConfirmation elements that should be included in the assertion.
     *
     * @param array \SAML2\XML\saml\SubjectConfirmation[]
     * @return void
     */
    private function setSubjectConfirmation(array $subjectConfirmation): void
    {
        $this->subjectConfirmation = $subjectConfirmation;
    }


    /**
     * Convert XML into an AuthnRequest
     *
     * @param \DOMElement $xml The XML element we should load
     * @return \SAML2\XML\samlp\AuthnRequest
     * @throws \InvalidArgumentException if the qualified name of the supplied element is wrong
     */
    public static function fromXML(DOMElement $xml): object
    {
        Assert::same($xml->localName, 'AuthnRequest');
        Assert::same($xml->namespaceURI, AuthnRequest::NS);

        $id = self::getAttribute($xml, 'ID');
        $version = self::getAttribute($xml, 'Version');
        $issueInstant = Utils::xsDateTimeToTimestamp(self::getAttribute($xml, 'IssueInstant'));
        $inResponseTo = self::getAttribute($xml, 'InResponseTo', null);
        $destination = self::getAttribute($xml, 'Destination', null);
        $consent = self::getAttribute($xml, 'Consent', null);

        $forceAuthn = self::getBooleanAttribute($xml, 'ForceAuthn', 'false');
        $isPassive = self::getBooleanAttribute($xml, 'IsPassive', 'false');

        $assertionConsumerServiceUrl = self::getAttribute($xml, 'AssertionConsumerServiceURL', null);
        $protocolBinding = self::getAttribute($xml, 'ProtocolBinding', null);

        $attributeConsumingServiceIndex = self::getAttribute($xml, 'AttributeConsumingServiceIndex', null);
        if ($attributeConsumingServiceIndex !== null) {
            $attributeConsumingServiceIndex = intval($attributeConsumingServiceIndex);
        }

        $assertionConsumerServiceIndex = self::getAttribute($xml, 'AssertionConsumerServiceIndex', null);
        if ($assertionConsumerServiceIndex !== null) {
            $assertionConsumerServiceIndex = intval($assertionConsumerServiceIndex);
        }

        $providerName = self::getAttribute($xml, 'ProviderName', null);

        $subject = Subject::getChildrenOfClass($xml);
        Assert::maxCount($subject, 1, 'Only one saml:Subject element is allowed.');

        $issuer = Issuer::getChildrenOfClass($xml);
        Assert::maxCount($issuer, 1, 'Only one saml:Issuer element is allowed.');

        $requestedAuthnContext = RequestedAuthnContext::getChildrenOfClass($xml);
        Assert::maxCount($requestedAuthnContext, 1, 'Only one samlp:RequestedAuthnContext element is allowed.');

        $extensions = Extensions::getChildrenOfClass($xml);
        Assert::maxCount($extensions, 1, 'Only one samlp:Extensions element is allowed.');

        $signature = Signature::getChildrenOfClass($xml);
        Assert::maxCount($signature, 1, 'Only one ds:Signature element is allowed.');

        $request = new self(
            array_pop($requestedAuthnContext),
            array_pop($subject),
            $forceAuthn,
            $isPassive,
            $assertionConsumerServiceUrl,
            $protocolBinding,
            $attributeConsumingServiceIndex,
            $providerName,
            array_pop($issuer),
            $id,
            $version,
            $issueInstant,
            $destination,
            $consent,
            array_pop($extensions)
        );

        if (!empty($signature)) {
            $request->setSignature($signature[0]);
        }

        return $request;

    }


    /**
     * Convert this authentication request to an XML element.
     *
     * @return \DOMElement This authentication request.
     */
    public function toXML(?DOMElement $parent = null): DOMElement
    {
        Assert::null($parent);

        $parent = parent::toXML($parent);

        if ($this->forceAuthn) {
            $parent->setAttribute('ForceAuthn', 'true');
        }

        if (!empty($this->ProviderName)) {
            $parent->setAttribute('ProviderName', $this->ProviderName);
        }

        if ($this->isPassive) {
            $parent->setAttribute('IsPassive', 'true');
        }

        if ($this->assertionConsumerServiceIndex !== null) {
            $parent->setAttribute('AssertionConsumerServiceIndex', strval($this->assertionConsumerServiceIndex));
        } else {
            if ($this->assertionConsumerServiceURL !== null) {
                $parent->setAttribute('AssertionConsumerServiceURL', $this->assertionConsumerServiceURL);
            }
            if ($this->protocolBinding !== null) {
                $parent->setAttribute('ProtocolBinding', $this->protocolBinding);
            }
        }

        if ($this->attributeConsumingServiceIndex !== null) {
            $parent->setAttribute('AttributeConsumingServiceIndex', strval($this->attributeConsumingServiceIndex));
        }

        if ($this->subject !== null) {
            $this->subject->toXML($parent);
        }

        if ($this->nameIdPolicy !== null) {
            if (!$this->nameIdPolicy->isEmptyElement()) {
                $this->nameIdPolicy->toXML($parent);
            }
        }

        $this->addConditions($parent);

        if (!empty($this->requestedAuthnContext)) {
            $this->requestedAuthnContext->toXML($parent);
        }

        if ($this->ProxyCount !== null || count($this->IDPList) > 0 || count($this->RequesterID) > 0) {
            $scoping = $this->document->createElementNS(Constants::NS_SAMLP, 'Scoping');
            $parent->appendChild($scoping);
            if ($this->ProxyCount !== null) {
                $scoping->setAttribute('ProxyCount', strval($this->ProxyCount));
            }
            if (count($this->IDPList) > 0) {
                $idplist = $this->document->createElementNS(Constants::NS_SAMLP, 'IDPList');
                foreach ($this->IDPList as $provider) {
                    $idpEntry = $this->document->createElementNS(Constants::NS_SAMLP, 'IDPEntry');
                    if (is_string($provider)) {
                        $idpEntry->setAttribute('ProviderID', $provider);
                    } elseif (is_array($provider)) {
                        foreach ($provider as $attribute => $value) {
                            if (
                                in_array($attribute, [
                                    'ProviderID',
                                    'Loc',
                                    'Name'
                                ], true)
                            ) {
                                $idpEntry->setAttribute($attribute, $value);
                            }
                        }
                    }
                    $idplist->appendChild($idpEntry);
                }
                $scoping->appendChild($idplist);
            }

            Utils::addStrings($scoping, Constants::NS_SAMLP, 'RequesterID', false, $this->RequesterID);
        }

        return $this->signElement($parent);
    }


    /**
     * Add a Conditions-node to the request.
     *
     * @param \DOMElement $root The request element we should add the conditions to.
     * @return void
     */
    private function addConditions(DOMElement $root): void
    {
        if (!empty($this->audiences)) {
            $document = $root->ownerDocument;

            $conditions = $document->createElementNS(Constants::NS_SAML, 'saml:Conditions');
            $root->appendChild($conditions);

            $ar = $document->createElementNS(Constants::NS_SAML, 'saml:AudienceRestriction');
            $conditions->appendChild($ar);

            Utils::addStrings($ar, Constants::NS_SAML, 'saml:Audience', false, $this->getAudiences());
        }
    }
}
