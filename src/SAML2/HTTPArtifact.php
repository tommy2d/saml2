<?php

declare(strict_types=1);

namespace SimpleSAML\SAML2;

use Exception;
use SimpleSAML\Assert\Assert;
use SimpleSAML\Configuration;
use SimpleSAML\Metadata\MetaDataStorageHandler;
use SimpleSAML\Module\saml\Message as MSG;
use SimpleSAML\SAML2\Utilities\Temporal;
use SimpleSAML\SAML2\XML\saml\Issuer;
use SimpleSAML\SAML2\XML\samlp\AbstractMessage;
use SimpleSAML\SAML2\XML\samlp\ArtifactResolve;
use SimpleSAML\SAML2\XML\samlp\ArtifactResponse;
use SimpleSAML\Store;
use SimpleSAML\Utils\HTTP;
use SimpleSAML\XMLSecurity\XMLSecurityKey;

use function array_key_exists;
use function base64_decode;
use function base64_encode;
use function bin2hex;
use function hexdec;
use function openssl_random_pseudo_bytes;
use function pack;
use function sha1;
use function substr;
use function var_export;

/**
 * Class which implements the HTTP-Artifact binding.
 *
 * @package simplesamlphp/saml2
 */
class HTTPArtifact extends Binding
{
    /**
     * @psalm-suppress UndefinedDocblockClass
     * @psalm-suppress UndefinedClass
     * @var \SimpleSAML\Configuration
     */
    private Configuration $spMetadata;


    /**
     * Create the redirect URL for a message.
     *
     * @param  \SimpleSAML\SAML2\XML\samlp\AbstractMessage $message The message.
     * @throws \Exception
     * @return string        The URL the user should be redirected to in order to send a message.
     */
    public function getRedirectURL(AbstractMessage $message): string
    {
        /** @psalm-suppress UndefinedClass */
        $store = Store::getInstance();
        if ($store === false) {
            throw new Exception('Unable to send artifact without a datastore configured.');
        }

        $generatedId = pack('H*', bin2hex(openssl_random_pseudo_bytes(20)));
        $issuer = $message->getIssuer();
        if ($issuer === null) {
            throw new Exception('Cannot get redirect URL, no Issuer set in the message.');
        }
        $artifact = base64_encode("\x00\x04\x00\x00" . sha1($issuer->getContent(), true) . $generatedId);
        $artifactData = $message->toXML();
        $artifactDataString = $artifactData->ownerDocument->saveXML($artifactData);

        $store->set('artifact', $artifact, $artifactDataString, Temporal::getTime() + 15 * 60);

        $destination = $message->getDestination();
        if ($destination === null) {
            throw new Exception('Cannot get redirect URL, no destination set in the message.');
        }

        $params = ['SAMLart' => $artifact];

        $relayState = $message->getRelayState();
        if ($relayState !== null) {
            $params['RelayState'] = $relayState;
        }

        /** @psalm-suppress UndefinedClass */
        return HTTP::addURLparameters($destination, $params);
    }


    /**
     * Send a SAML 2 message using the HTTP-Redirect binding.
     *
     * Note: This function never returns.
     *
     * @param \SimpleSAML\SAML2\XML\samlp\AbstractMessage $message The message we should send.
     * @throws \Exception
     */
    public function send(AbstractMessage $message): void
    {
        $destination = $this->getRedirectURL($message);
        Utils::getContainer()->redirect($destination);
    }


    /**
     * Receive a SAML 2 message sent using the HTTP-Artifact binding.
     *
     * Throws an exception if it is unable receive the message.
     *
     * @throws \Exception
     * @return \SimpleSAML\SAML2\XML\samlp\AbstractMessage The received message.
     *
     * @throws \SimpleSAML\Assert\AssertionFailedException if assertions are false
     */
    public function receive(): AbstractMessage
    {
        if (array_key_exists('SAMLart', $_REQUEST)) {
            $artifact = base64_decode($_REQUEST['SAMLart']);
            $endpointIndex = bin2hex(substr($artifact, 2, 2));
            $sourceId = bin2hex(substr($artifact, 4, 20));
        } else {
            throw new Exception('Missing SAMLart parameter.');
        }

        /** @psalm-suppress UndefinedClass */
        $metadataHandler = MetaDataStorageHandler::getMetadataHandler();

        $idpMetadata = $metadataHandler->getMetaDataConfigForSha1($sourceId, 'saml20-idp-remote');

        if ($idpMetadata === null) {
            throw new Exception('No metadata found for remote provider with SHA1 ID: ' . var_export($sourceId, true));
        }

        $endpoint = null;
        foreach ($idpMetadata->getEndpoints('ArtifactResolutionService') as $ep) {
            if ($ep['index'] === hexdec($endpointIndex)) {
                $endpoint = $ep;
                break;
            }
        }

        if ($endpoint === null) {
            throw new Exception('No ArtifactResolutionService with the correct index.');
        }

        Utils::getContainer()->getLogger()->debug(
            "ArtifactResolutionService endpoint being used is := " . $endpoint['Location']
        );

        /**
         * @psalm-suppress UndefinedClass
         * @psalm-suppress DocblockTypeContradiction
         */
        Assert::notEmpty($this->spMetadata, 'Cannot process received message without SP metadata.');

        /**
         * Set the request attributes
         */
        $issuer = new Issuer($this->spMetadata->getString('entityid'));

        // Construct the ArtifactResolve Request
        $ar = new ArtifactResolve($_REQUEST['SAMLart'], $issuer, null, null, null, $endpoint['Location']);

        // sign the request
        /** @psalm-suppress UndefinedClass */
        MSG::addSign($this->spMetadata, $idpMetadata, $ar); // Shoaib - moved from the SOAPClient.

        $soap = new SOAPClient();

        // Send message through SoapClient
        /** @var \SimpleSAML\SAML2\XML\samlp\ArtifactResponse $artifactResponse */
        $artifactResponse = $soap->send($ar, $this->spMetadata);

        if (!$artifactResponse->isSuccess()) {
            throw new Exception('Received error from ArtifactResolutionService.');
        }

        $samlResponse = $artifactResponse->getMessage();
        if ($samlResponse === null) {
            /* Empty ArtifactResponse - possibly because of Artifact replay? */

            throw new Exception('Empty ArtifactResponse received, maybe a replay?');
        }

        $samlResponse->addValidator([get_class($this), 'validateSignature'], $artifactResponse);

        if (isset($_REQUEST['RelayState'])) {
            $samlResponse->setRelayState($_REQUEST['RelayState']);
        }

        return $samlResponse;
    }


    /**
     * @param \SimpleSAML\Configuration $sp
     *
     *
     * @psalm-suppress UndefinedClass
     */
    public function setSPMetadata(Configuration $sp): void
    {
        $this->spMetadata = $sp;
    }


    /**
     * A validator which returns true if the ArtifactResponse was signed with the given key
     *
     * @param \SimpleSAML\SAML2\XML\samlp\ArtifactResponse $message
     * @param \SimpleSAML\XMLSecurity\XMLSecurityKey $key
     * @return bool
     */
    public static function validateSignature(ArtifactResponse $message, XMLSecurityKey $key): bool
    {
        // @todo verify if this works and/or needs to do anything more. Ref. HTTPRedirect binding
        return $message->validate($key);
    }
}
