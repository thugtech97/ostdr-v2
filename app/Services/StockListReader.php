<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Reads the MCD stock list workbook used by "Update Items": headers on row 5, items from
 * row 6 until the first blank stock code, the same layout legacy expects.
 */
class StockListReader
{
    public const HEADERS = [
        'Stock Code', 'Stock Description', 'OEM ID', 'UOM', 'Stock Type', 'Inv Code', 'Average Unit Price',
        'Average Monthly UR', 'On Hand Qty As On (OH)', 'MIN', 'MAX',
    ];

    private const HEADER_ROW = 4;

    /**
     * @return list<array{code: string, description: ?string, oem: ?string, uom: ?string}>
     *
     * @throws InvalidArgumentException when the sheet does not have the stock list headers
     */
    public function read(UploadedFile $file): array
    {
        $rows = IOFactory::load($file->getRealPath())->getActiveSheet()->toArray();

        $headers = array_map(
            // The month in "Average Monthly UR (as of June)" changes with every export.
            fn ($header) => trim(preg_replace('/\(as of [^)]*\)/i', '', strtoupper(trim((string) $header)))),
            array_slice($rows[self::HEADER_ROW] ?? [], 0, count(self::HEADERS)),
        );

        if ($headers !== array_map('strtoupper', self::HEADERS)) {
            throw new InvalidArgumentException('Invalid headers.');
        }

        $items = [];

        for ($i = self::HEADER_ROW + 1; isset($rows[$i]); $i++) {
            $code = trim((string) $rows[$i][0]);

            if ($code === '') {
                break;
            }

            $items[] = [
                'code' => $code,
                'description' => $this->text($rows[$i][1] ?? null),
                'oem' => $this->text($rows[$i][2] ?? null),
                'uom' => $this->text($rows[$i][3] ?? null),
            ];
        }

        return $items;
    }

    private function text(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
