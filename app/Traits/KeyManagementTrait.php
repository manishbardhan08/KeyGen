<?php

namespace App\Traits;

trait KeyManagementTrait
{
    /**
     * Keys created per day for the last N days (oldest → newest), for the
     * dashboard mini chart.
     *
     * @return list<array{day:string, label:string, count:int}>
     */
    protected function dailyCounts(int $uid, int $days = 14): array
    {
        $db    = \Config\Database::connect();
        $start = date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' days'));

        $rows = $db->table('keys')
            ->select('DATE(created_at) AS d, COUNT(*) AS c', false)
            ->where('created_by', $uid)
            ->where('created_at >=', $start)
            ->groupBy('DATE(created_at)', false)
            ->get()->getResultArray();

        $map = [];
        foreach ($rows as $r) {
            $map[(string) $r['d']] = (int) $r['c'];
        }

        $out = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $ts    = strtotime("-{$i} days");
            $out[] = [
                'day'   => date('Y-m-d', $ts),
                'label' => date('d', $ts),
                'count' => $map[date('Y-m-d', $ts)] ?? 0,
            ];
        }
        return $out;
    }

    /**
     * Build a CSV string from rows, guarding against spreadsheet formula
     * injection (a field starting with = + - @ is prefixed with an apostrophe).
     *
     * @param list<string>                     $header
     * @param array<int, array<string, mixed>> $rows
     */
    protected function toCsv(array $header, array $rows): string
    {
        $safe = static function ($v): string {
            $v = (string) ($v ?? '');
            if ($v !== '' && in_array($v[0], ['=', '+', '-', '@'], true)) {
                $v = "'" . $v;
            }
            return $v;
        };

        $fh = fopen('php://temp', 'r+');
        fputcsv($fh, $header);
        foreach ($rows as $r) {
            fputcsv($fh, array_map($safe, array_values($r)));
        }
        rewind($fh);
        $csv = (string) stream_get_contents($fh);
        fclose($fh);

        return $csv;
    }
}
