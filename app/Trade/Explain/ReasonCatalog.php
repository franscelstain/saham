<?php

namespace App\Trade\Explain;

class ReasonCatalog
{
    public static function decisionLabel(int $decisionCode): string
    {
        $map = self::decisionMap();

        return $map[$decisionCode] ?? ('Unknown (' . $decisionCode . ')');
    }

    /**
     * Map signal_code -> label (versi awal).
     * Jangan taruh logika trading di sini. Ini hanya label.
     */
    public static function signalLabel(int $signalCode): string
    {
        $map = self::signalMap();

        return $map[$signalCode] ?? ('Unknown (' . $signalCode . ')');
    }

    /**
     * Map volume_label_code -> label (versi awal).
     */
    public static function volumeLabel(int $volumeLabelCode): string
    {
        $map = self::volumeMap();

        return $map[$volumeLabelCode] ?? ('Unknown (' . $volumeLabelCode . ')');
    }

    /**
     * Optional: CSS class hint (biar UI mudah kasih badge color).
     * Bukan wajib, tapi berguna dan masih “presentasi”.
     */
    public static function decisionBadgeClass(int $decisionCode): string
    {
        $map = [
            1 => 'bg-red-700 text-white',
            2 => 'bg-rose-600 text-white',
            3 => 'bg-amber-500 text-slate-900',
            4 => 'bg-sky-600 text-white',
            5 => 'bg-emerald-600 text-white',
        ];

        return $map[$decisionCode] ?? 'bg-slate-500 text-white';
    }

    public static function signalBadgeClass(int $signalCode): string
    {
        $map = [
            0 => 'bg-slate-400 text-slate-900',
            1 => 'bg-slate-500 text-white',   // Base / Sideways
            2 => 'bg-sky-600 text-white',     // Early Uptrend
            3 => 'bg-green-600 text-white', // Accumulation
            4 => 'bg-emerald-600 text-white', // Breakout
            5 => 'bg-emerald-700 text-white',   // Strong Breakout
            6 => 'bg-cyan-600 text-white', // Breakout Retest
            7 => 'bg-blue-600 text-white',  // Pullback Healthy
            8 => 'bg-rose-600 text-white',  // Distribution
            9 => 'bg-orange-600 text-white',     // Climax / Euphoria
            10 => 'bg-red-800 text-white',    // False Breakout
        ];

        return $map[$signalCode] ?? 'bg-slate-500 text-white';
    }

    public static function volumeBadgeClass(int $volumeLabelCode): string
    {
        $map = [
            1 => 'bg-slate-400 text-white',   // Dormant
            2 => 'bg-slate-300 text-slate-900', // Ultra Dry
            3 => 'bg-slate-500 text-white',   // Quiet
            4 => 'bg-slate-600 text-white',   // Normal
            5 => 'bg-sky-600 text-white',     // Early Interest
            6 => 'bg-emerald-600 text-white', // Volume Burst / Accumulation
            7 => 'bg-emerald-700 text-white', // Strong Burst / Breakout
            8 => 'bg-orange-600 text-white', // Climax / Euphoria
        ];

        return $map[$volumeLabelCode] ?? 'bg-slate-500 text-white';
    }

    /**
     * NOTE:
     * Isi mapping di bawah harus kamu sesuaikan dengan definisi kode yang kamu pakai saat compute indikator.
     * Kalau kamu belum yakin kode mana = apa, isi dulu sebatas yang kamu tahu, sisanya Unknown().
     */
    private static function decisionMap(): array
    {
        return [
            1 => 'False Breakout / Batal',
            2 => 'Hindari',
            3 => 'Hati - Hati',
            4 => 'Perlu Konfirmasi',
            5 => 'Layak Beli'
        ];
    }

    private static function signalMap(): array
    {
        return [
            0 => 'Unknown',
            1 => 'Base / Sideways', // Konsolidasi
            2 => 'Early Uptrend', // Trend mulai terbentuk
            3 => 'Accumulation', // Harga stabil + volume menguat
            4 => 'Breakout', // Tembus resistance
            5 => 'Strong Breakout', // Breakout kuat + close near high + volume
            6 => 'Breakout Retest', // Breakout lalu uji ulang level
            7 => 'Pullback Healthy', // Koreksi wajar, masih uptrend
            8 => 'Distribution', // Volume tinggi tapi gagal naik / melemah
            9 => 'Climax / Euphoria', // Overheated / rawan reversal
            10 => 'False Breakout', // Breakout gagal / jebakan
        ];
    }

    private static function volumeMap(): array
    {
        return [
            1 => 'Dormant',
            2 => 'Ultra Dry',
            3 => 'Quiet',
            4 => 'Normal',
            5 => 'Early Interest',
            6 => 'Volume Burst / Accumulation',
            7 => 'Strong Burst / Breakout',
            8 => 'Climax / Euphoria'
        ];
    }
}
