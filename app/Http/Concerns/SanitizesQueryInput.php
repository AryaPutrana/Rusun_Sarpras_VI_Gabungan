<?php

namespace App\Http\Concerns;

use Illuminate\Http\Request;

/**
 * Sanitasi parameter query pada halaman daftar/laporan.
 *
 * Parameter seperti ?search[]=x atau ?start_date[]=x dikirim browser sebagai array.
 * Jika langsung dipakai di query, PHP memunculkan warning "Array to string conversion"
 * yang oleh Laravel diubah menjadi ErrorException, sehingga halaman balas HTTP 500.
 * Helper ini memastikan hanya string skalar yang lolos ke query.
 */
trait SanitizesQueryInput
{
    /**
     * Ambil parameter query sebagai string skalar, atau null bila kosong/tidak valid.
     */
    protected function scalarQuery(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        if (is_array($value) || is_object($value) || is_null($value)) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
