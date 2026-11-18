import { createHmac } from 'node:crypto';

// RFC 6238 TOTP (SHA-1, 30 s, 6 digits) - what the app and the scheb/2fa bundle use
export function totp(base32Secret: string, time = Date.now()): string {
    const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    let bits = '';
    for (const char of base32Secret.replace(/[\s=]/g, '').toUpperCase()) {
        bits += alphabet.indexOf(char).toString(2).padStart(5, '0');
    }
    const key = Buffer.from(bits.match(/.{8}/g)!.map(byte => parseInt(byte, 2)));

    const counter = Buffer.alloc(8);
    counter.writeBigUInt64BE(BigInt(Math.floor(time / 1000 / 30)));

    const hmac = createHmac('sha1', key).update(counter).digest();
    const offset = hmac[hmac.length - 1] & 0x0f;

    return ((hmac.readUInt32BE(offset) & 0x7fffffff) % 1_000_000).toString().padStart(6, '0');
}
