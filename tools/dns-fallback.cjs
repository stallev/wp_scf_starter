/**
 * Preloaded into wp-env (NODE_OPTIONS=--require) by tools/wp-env.mjs.
 *
 * wp-env decides "online/offline" with dns.promises.resolve('WordPress.org'), which queries
 * DNS servers directly (c-ares). Behind some VPN/corporate resolvers that times out while the
 * OS resolver works, so wp-env wrongly goes offline and skips downloads. Ask the OS resolver
 * (dns.lookup / getaddrinfo) first and only then fall back to the original resolve.
 */
const dns = require('node:dns');

const original = dns.promises.resolve.bind(dns.promises);

dns.promises.resolve = async function resolveViaLookup(hostname, rrtype, ...rest) {
  if (rrtype === undefined || rrtype === 'A' || rrtype === 'AAAA') {
    const found = await dns.promises.lookup(hostname, { all: true, family: rrtype === 'AAAA' ? 6 : 4 }).catch(() => null);
    if (found && found.length) {
      return found.map((entry) => entry.address);
    }
  }
  return original(hostname, rrtype, ...rest);
};
