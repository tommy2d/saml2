<?php

declare(strict_types=1);

namespace SimpleSAML\SAML2\Certificate;

use function chunk_split;
use function preg_replace;

/**
 * Specific Certificate Key.
 */
class X509 extends Key
{
    /**
     * @param string $certificateContents
     * @return \SimpleSAML\SAML2\Certificate\X509
     */
    public static function createFromCertificateData(string $certificateContents): X509
    {
        $data = [
            'encryption'      => true,
            'signing'         => true,
            'type'            => 'X509Certificate',
            'X509Certificate' => $certificateContents
        ];

        return new self($data);
    }


    /**
     * {@inheritdoc} Best place to ensure the logic is encapsulated in a single place
     *
     * @param mixed $offset
     * @param mixed $value
     *
     * Type hint not possible due to upstream method signature
     */
    public function offsetSet($offset, $value): void
    {
        if ($offset === 'X509Certificate') {
            $value = preg_replace('~\s+~', '', $value);
        }

        parent::offsetSet($offset, $value);
    }


    /**
     * Get the certificate representation
     *
     * @return string
     */
    public function getCertificate(): string
    {
        return "-----BEGIN CERTIFICATE-----\n"
                . chunk_split($this->keyData['X509Certificate'], 64)
                . "-----END CERTIFICATE-----\n";
    }
}
