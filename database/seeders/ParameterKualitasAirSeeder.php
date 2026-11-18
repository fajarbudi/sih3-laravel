<?php

namespace Database\Seeders;

use App\Models\ParameterKualitasAir;
use Illuminate\Database\Seeder;

/**
 * Parameter kualitas air. `kode` = nama kolom lama ana_data_kualitas_air tanpa prefix
 * "kualitas_air_", dipakai oleh sih3:import untuk memetakan nilai.
 * Satuan mengikuti kelaziman (PP 22/2021); periksa ulang dengan tim lab bila perlu.
 */
class ParameterKualitasAirSeeder extends Seeder
{
    public const PARAMETER = [
        // kode => [nama, satuan]
        'temperatur' => ['Temperatur', '°C'],
        'dhl' => ['Daya Hantar Listrik', 'µS/cm'],
        'tds' => ['TDS', 'mg/L'],
        'salinity' => ['Salinitas', '‰'],
        'turbidity' => ['Kekeruhan', 'NTU'],
        'ph' => ['pH', null],
        'alkalinity' => ['Alkalinitas', 'mg/L'],
        'acidity' => ['Asiditas', 'mg/L'],
        'do' => ['DO (Oksigen Terlarut)', 'mg/L'],
        'cod' => ['COD', 'mg/L'],
        'bod' => ['BOD', 'mg/L'],
        'cl' => ['Klorida (Cl)', 'mg/L'],
        'no3' => ['Nitrat (NO3)', 'mg/L'],
        'no2' => ['Nitrit (NO2)', 'mg/L'],
        'so4' => ['Sulfat (SO4)', 'mg/L'],
        'fe' => ['Besi (Fe)', 'mg/L'],
        'cu' => ['Tembaga (Cu)', 'mg/L'],
        'coli' => ['Fecal Coliform', 'MPN/100mL'],
        'total_coli' => ['Total Coliform', 'MPN/100mL'],
        'tss' => ['TSS', 'mg/L'],
        'kesadahan' => ['Kesadahan', 'mg/L'],
        'ca' => ['Kalsium (Ca)', 'mg/L'],
        'mg' => ['Magnesium (Mg)', 'mg/L'],
        'kmno4' => ['KMnO4', 'mg/L'],
        'orto' => ['Ortofosfat', 'mg/L'],
        'nh4' => ['Amonium (NH4)', 'mg/L'],
        'lemak' => ['Minyak & Lemak', 'mg/L'],
        'fenol' => ['Fenol', 'mg/L'],
        'warna' => ['Warna', 'Pt-Co'],
        'cr' => ['Kromium (Cr)', 'mg/L'],
        'pb' => ['Timbal (Pb)', 'mg/L'],
        'cianida' => ['Sianida', 'mg/L'],
        'ni' => ['Nikel (Ni)', 'mg/L'],
        'zn' => ['Seng (Zn)', 'mg/L'],
        'mn' => ['Mangan (Mn)', 'mg/L'],
        'se' => ['Selenium (Se)', 'mg/L'],
        'ba' => ['Barium (Ba)', 'mg/L'],
        'co' => ['Kobalt (Co)', 'mg/L'],
        'hg' => ['Raksa (Hg)', 'mg/L'],
        'phospat' => ['Fosfat Total', 'mg/L'],
        'muka_air' => ['Muka Air', 'm'],
        'debit' => ['Debit', 'm³/detik'],
        'nh3n' => ['Amonia (NH3-N)', 'mg/L'],
        'deterjen' => ['Deterjen (MBAS)', 'mg/L'],
    ];

    public function run(): void
    {
        $urutan = 0;

        foreach (self::PARAMETER as $kode => [$nama, $satuan]) {
            ParameterKualitasAir::updateOrCreate(
                ['kode' => $kode],
                ['nama' => $nama, 'satuan' => $satuan, 'urutan' => ++$urutan],
            );
        }
    }
}
