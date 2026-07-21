<?php

namespace App\Support;

/**
 * The small amount of IPv4 arithmetic IPAM needs: what a subnet covers, how
 * many hosts fit, and whether an address falls inside it. IPv4 only — the
 * management network this was built for is v4, and v6 subnets are not
 * enumerated the same way.
 */
final class Cidr
{
    public function __construct(
        public readonly int $network,
        public readonly int $prefix,
    ) {}

    /**
     * Parses "10.40.0.0/24" into a network address and prefix, or null when it
     * is not a well-formed IPv4 CIDR. The address is masked to the network, so
     * "10.40.0.5/24" and "10.40.0.0/24" describe the same subnet.
     */
    public static function parse(string $cidr): ?self
    {
        if (! str_contains($cidr, '/')) {
            return null;
        }

        [$address, $prefix] = explode('/', $cidr, 2);

        if (! ctype_digit($prefix) || (int) $prefix > 32) {
            return null;
        }

        $long = self::toLong($address);

        if ($long === null) {
            return null;
        }

        $prefix = (int) $prefix;
        $mask = self::mask($prefix);

        return new self($long & $mask, $prefix);
    }

    public static function toLong(string $address): ?int
    {
        $long = filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);

        return $long === false ? null : (int) sprintf('%u', ip2long($address));
    }

    public static function toString(int $long): string
    {
        return long2ip($long);
    }

    public static function mask(int $prefix): int
    {
        return $prefix === 0 ? 0 : (0xFFFFFFFF << (32 - $prefix)) & 0xFFFFFFFF;
    }

    public function broadcast(): int
    {
        return $this->network | (~self::mask($this->prefix) & 0xFFFFFFFF);
    }

    /**
     * The usable host addresses: network and broadcast are excluded on a /24 or
     * wider, while a /31 and /32 have none to spare.
     */
    public function firstHost(): int
    {
        return $this->prefix >= 31 ? $this->network : $this->network + 1;
    }

    public function lastHost(): int
    {
        return $this->prefix >= 31 ? $this->broadcast() : $this->broadcast() - 1;
    }

    public function hostCount(): int
    {
        if ($this->prefix >= 31) {
            return 2 ** (32 - $this->prefix);
        }

        return max(0, $this->lastHost() - $this->firstHost() + 1);
    }

    public function contains(int $long): bool
    {
        return $long >= $this->network && $long <= $this->broadcast();
    }

    public function label(): string
    {
        return self::toString($this->network).'/'.$this->prefix;
    }
}
