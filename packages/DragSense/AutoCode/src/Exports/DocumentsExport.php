<?php 

namespace DragSense\AutoCode\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DocumentsExport implements FromCollection, WithHeadings, WithTitle, WithStyles
{
    protected $documents;
    protected $fields;
    protected $title;
    protected $total;

    public function __construct(Collection $documents, array $fields, string $title, $total)
    {
        $this->documents = $documents;
        $this->fields = $fields;
        $this->title = $title;
        $this->total = $total;
    }

    /**
     * Return a collection of documents
     *
     * @return Collection
     */
    public function collection(): Collection
    {
        return $this->documents;
    }

    /**
     * Define the headings for the Excel sheet
     *
     * @return array
     */
    public function headings(): array
    {
        return $this->fields;
    }

    /**
     * Apply styles to the worksheet
     *
     * @param Worksheet $sheet
     * @return array
     */
    public function styles(Worksheet $sheet)
    {
        // Bold the first row (headings)
        $sheet->getStyle('1:1')->getFont()->setBold(true)->setSize(14);

        // Calculate the row where the total will be displayed
        $totalRow = $this->documents->count() + 2;

        // Get the last column for placing the total dynamically
        $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($this->fields));

        // Set 'Total' label and the total value
        $sheet->setCellValue("D$totalRow", 'Total');
        $sheet->setCellValue("F$totalRow", $this->total);

        // Bold the total row
        $sheet->getStyle("D$totalRow:$lastColumn$totalRow")->getFont()->setBold(true)->setSize(14);
    }

    /**
     * Set the title for the worksheet
     *
     * @return string
     */
    public function title(): string
    {
        return $this->title;
    }
}
