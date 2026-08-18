<?php

namespace App\Http\Controllers;

use App\Services\SalesDepositReport;
use Illuminate\Http\Request;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SalesDepositExportController extends Controller
{
    public function __invoke(Request $request, SalesDepositReport $report): BinaryFileResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $filters = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'sales_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);
        $rows = $report->rows($filters);
        $summary = $report->summary($rows);
        $temporaryFile = tempnam(sys_get_temp_dir(), 'setoran-sales-');
        $options = $this->options();
        $styles = $this->styles();
        $writer = new Writer($options);
        $writer->openToFile($temporaryFile);
        $writer->getCurrentSheet()->setName('Rekap Setoran Sales');

        $writer->addRow(Row::fromValues(['REKAP SETORAN SALES PANCOR MAS'], $styles['title'])->setHeight(28));
        $writer->addRow(Row::fromValues([
            'Periode '.$filters['start_date'].' s.d. '.$filters['end_date'].' - Hanya setoran yang telah disetujui Admin',
        ], $styles['subtitle'])->setHeight(22));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues([
            'SETORAN SEHARUSNYA', '', 'UANG FISIK DITERIMA', '', 'SELISIH UANG', '', 'HUTANG TOKO', '', 'JUMLAH REKAP', '',
        ], $styles['summary_label']));
        $writer->addRow(Row::fromValuesWithStyles([
            $summary['expected'], '', $summary['actual'], '', $summary['difference'], '', $summary['debt'], '', $summary['records'], '',
        ], null, [
            0 => $styles['summary_money'], 2 => $styles['summary_money'], 4 => $styles['summary_money'],
            6 => $styles['summary_money'], 8 => $styles['summary_number'],
        ]));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues([
            'Tanggal', 'Sales', 'Nomor Pengembalian', 'Toko', 'Produk Laku (Bungkus)',
            'Setoran Seharusnya', 'Uang Fisik Diterima', 'Selisih Uang', 'Hutang Toko', 'Kekurangan Stok (Bungkus)',
        ], $styles['header'])->setHeight(32));

        foreach ($rows->values() as $index => $settlement) {
            $writer->addRow(Row::fromValuesWithStyles([
                $settlement->settlement_date->format('d/m/Y'),
                $this->safeText($settlement->sales->name),
                $this->safeText($settlement->returnTransfer?->number ?? '-'),
                $settlement->total_stores,
                $settlement->total_sold_pieces,
                $settlement->total_collection,
                $settlement->verified_collection,
                $settlement->cash_difference,
                (int) $settlement->total_debt,
                $settlement->stock_shortage_pieces,
            ], $index % 2 === 1 ? $styles['alternate_row'] : $styles['data_row'], [
                5 => $styles['currency'], 6 => $styles['currency'], 7 => $styles['currency'], 8 => $styles['currency'],
            ]));
        }

        $writer->addRow(Row::fromValuesWithStyles([
            'TOTAL PERIODE', '', '', $summary['stores'], '', $summary['expected'], $summary['actual'], $summary['difference'], $summary['debt'], $summary['stock_shortage'],
        ], null, [
            0 => $styles['total_label'], 1 => $styles['total_label'], 2 => $styles['total_label'],
            3 => $styles['total_label'], 4 => $styles['total_label'], 5 => $styles['total_money'],
            6 => $styles['total_money'], 7 => $styles['total_money'], 8 => $styles['total_money'], 9 => $styles['total_label'],
        ]));
        $writer->close();

        $filename = 'rekap-setoran-sales-'.$filters['start_date'].'-'.$filters['end_date'].'.xlsx';

        return response()->download($temporaryFile, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    private function options(): Options
    {
        $options = new Options;
        $options->setColumnWidth(14, 1);
        $options->setColumnWidth(24, 2);
        $options->setColumnWidth(22, 3);
        $options->setColumnWidth(11, 4, 5, 8);
        $options->setColumnWidth(18, 6, 7, 9, 10);
        $options->mergeCells(0, 1, 9, 1);
        $options->mergeCells(0, 2, 9, 2);

        foreach ([[0, 1], [2, 3], [4, 5], [6, 7], [8, 9]] as [$start, $end]) {
            $options->mergeCells($start, 4, $end, 4);
            $options->mergeCells($start, 5, $end, 5);
        }

        return $options;
    }

    private function styles(): array
    {
        return [
            'title' => (new Style)->setFontBold()->setFontSize(17)->setFontColor('FFFFFF')->setBackgroundColor('3F2A1D')->setCellAlignment(CellAlignment::CENTER)->setCellVerticalAlignment(CellVerticalAlignment::CENTER),
            'subtitle' => (new Style)->setFontColor('374151')->setBackgroundColor('F3F4F6')->setCellAlignment(CellAlignment::CENTER),
            'summary_label' => (new Style)->setFontBold()->setFontColor('1F2937')->setBackgroundColor('E5E7EB')->setCellAlignment(CellAlignment::CENTER),
            'summary_money' => (new Style)->setFontBold()->setFontSize(13)->setFormat('"Rp" #,##0')->setCellAlignment(CellAlignment::CENTER),
            'summary_number' => (new Style)->setFontBold()->setFontSize(13)->setFormat('#,##0')->setCellAlignment(CellAlignment::CENTER),
            'header' => (new Style)->setFontBold()->setFontColor('FFFFFF')->setBackgroundColor('1F2937')->setShouldWrapText()->setCellAlignment(CellAlignment::CENTER)->setCellVerticalAlignment(CellVerticalAlignment::CENTER),
            'data_row' => (new Style)->setCellVerticalAlignment(CellVerticalAlignment::CENTER),
            'alternate_row' => (new Style)->setBackgroundColor('F3F4F6')->setCellVerticalAlignment(CellVerticalAlignment::CENTER),
            'currency' => (new Style)->setFormat('"Rp" #,##0'),
            'total_label' => (new Style)->setFontBold()->setFontColor('FFFFFF')->setBackgroundColor('15803D'),
            'total_money' => (new Style)->setFontBold()->setFontColor('FFFFFF')->setBackgroundColor('15803D')->setFormat('"Rp" #,##0'),
        ];
    }

    private function safeText(string $value): string
    {
        return preg_match('/^[=+\-@]/', $value) ? "'{$value}" : $value;
    }
}
