<?php
declare(strict_types=1);

namespace App;




final class StatementGenerator
{
    public function generate(StatementRequest $request): array
    {
        usleep(800000);
        $seed = hexdec(substr($request->id, 0, 6));
        $opening = ($seed % 200000) / 100;
        $operations = [
            ['date' => $request->dateFrom->format('Y-m-d'), 'description' => 'Входящий перевод', 'amount' => 12500.00],
            ['date' => $request->dateTo->format('Y-m-d'), 'description' => 'Оплата услуг', 'amount' => -1350.50],
        ];
        return [
            'period' => $request->dateFrom->format('d.m.Y') . ' — ' . $request->dateTo->format('d.m.Y'),
            'openingBalance' => $opening,
            'closingBalance' => $opening + 11149.50,
            'operations' => $operations,
        ];
    }
}
