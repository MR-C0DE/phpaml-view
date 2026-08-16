# Security policy

## Supported versions

Only the latest published beta is supported while AML View remains below 1.0.

## Production checklist

1. Generate `AML_VIEW_SECRET` from at least 32 cryptographically random bytes.
2. Keep the secret outside the repository and rotate it after exposure.
3. Pass a stable authenticated session identifier as the kernel audience.
4. Serve interactions over HTTPS only.
5. Keep PHPAML and AML View updated.
6. Return generic public errors and log technical details server-side.

Interaction tokens are signed, expire after one hour by default and are
one-time. The default nonce store uses the operating system temporary folder;
distributed applications should provide a shared `NonceStore` implementation.

## Reporting a vulnerability

Do not open a public issue for an unpatched vulnerability. Use GitHub's private
security advisory feature on the project repository.
