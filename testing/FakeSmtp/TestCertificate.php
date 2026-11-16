<?php

/*
 * A self-signed certificate for 127.0.0.1, for the fake SMTP server's TLS. A client only trusts it if it's told to
 * (with PHP's openssl.cafile setting), so the tests can check that a certificate the client can't verify is refused,
 * as well as that TLS works with one it can.
 */

namespace Programster\Phpmailer\Testing\FakeSmtp;


final readonly class TestCertificate
{
    private string $m_certificatePath;
    private string $m_privateKeyPath;


    private function __construct(string $certificatePath, string $privateKeyPath)
    {
        $this->m_certificatePath = $certificatePath;
        $this->m_privateKeyPath = $privateKeyPath;
    }


    /**
     * Create a new key and certificate, valid for a day, in a directory, as certificate.pem and private-key.pem.
     * @param string $directory - the directory to write them to.
     * @return TestCertificate
     * @throws \RuntimeException - if OpenSSL can't create them.
     */
    public static function createInDirectory(string $directory) : TestCertificate
    {
        // An elliptic curve key, as it's much quicker to create than an RSA one.
        $privateKey = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);

        if ($privateKey === false)
        {
            throw new \RuntimeException("Couldn't create a key for the test certificate: " . openssl_error_string());
        }

        // openssl_csr_new() takes the key by reference, so it's given a copy, to keep $privateKey's type.
        $keyForRequest = $privateKey;
        // For the IP address, rather than "localhost", so the client connects to exactly the address the server
        // listens on, rather than trying ::1 first, as "localhost" can resolve to that first.
        $request = openssl_csr_new(['commonName' => '127.0.0.1'], $keyForRequest, ['digest_alg' => 'sha256']);

        $certificate = ($request === false || $request === true)
            ? false
            : openssl_csr_sign($request, null, $privateKey, 1, ['digest_alg' => 'sha256']);

        $certificatePath = "{$directory}/certificate.pem";
        $privateKeyPath = "{$directory}/private-key.pem";

        $worked = (
               $certificate !== false
            && openssl_x509_export_to_file($certificate, $certificatePath)
            && openssl_pkey_export_to_file($privateKey, $privateKeyPath)
        );

        if (!$worked)
        {
            throw new \RuntimeException("Couldn't create the test certificate: " . openssl_error_string());
        }

        return new TestCertificate($certificatePath, $privateKeyPath);
    }


    # Accessors
    public function getCertificatePath() : string { return $this->m_certificatePath; }
    public function getPrivateKeyPath() : string { return $this->m_privateKeyPath; }
}
