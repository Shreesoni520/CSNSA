<?php

require_once __DIR__ . '/../../fpdf/fpdf.php';
require_once __DIR__ . '/funcionarios_actions.php';

function export_pdf_text(string $text): string
{
    $text = trim($text);
    if ($text === '') {
        return '';
    }

    $encoded = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $text);

    return $encoded !== false ? $encoded : $text;
}

function export_format_date(?string $value): string
{
    if ($value === null || $value === '' || $value === '0000-00-00') {
        return '-';
    }

    $ts = strtotime($value);
    if ($ts === false) {
        return $value;
    }

    return date('d/m/Y', $ts);
}

function export_funcionarios_ids(mysqli $conn, array $ids): array
{
    $funcionarios = funcionarios_load_list($conn);
    if (empty($ids)) {
        return $funcionarios;
    }

    $allowed = array_flip(array_map('intval', $ids));

    return array_values(array_filter($funcionarios, static function (array $row) use ($allowed): bool {
        return isset($allowed[(int) ($row['id'] ?? 0)]);
    }));
}

function export_picagens_periodo(array $input): array
{
    $tipo = $input['tipo_periodo'] ?? 'semana_atual';
    $dataInicio = trim((string) ($input['data_inicio'] ?? ''));
    $dataFim = trim((string) ($input['data_fim'] ?? ''));

    switch ($tipo) {
        case 'semana_passada':
            $hoje = date('Y-m-d', strtotime('-7 days'));
            $dataInicio = date('Y-m-d', strtotime('monday this week', strtotime($hoje)));
            $dataFim = date('Y-m-d', strtotime('sunday this week', strtotime($hoje)));
            $label = 'Semana passada';
            break;
        case 'mes_atual':
            $dataInicio = date('Y-m-01');
            $dataFim = date('Y-m-t');
            $label = 'Mes atual';
            break;
        case 'personalizado':
            if ($dataInicio === '' || $dataFim === '') {
                throw new InvalidArgumentException('Datas invalidas para o intervalo personalizado.');
            }
            $label = 'De ' . export_format_date($dataInicio) . ' ate ' . export_format_date($dataFim);
            break;
        case 'semana_atual':
        default:
            $hoje = date('Y-m-d');
            $dataInicio = date('Y-m-d', strtotime('monday this week', strtotime($hoje)));
            $dataFim = date('Y-m-d', strtotime('sunday this week', strtotime($hoje)));
            $label = 'Semana atual';
            break;
    }

    return [
        'inicio' => $dataInicio,
        'fim' => $dataFim,
        'label' => $label,
    ];
}

function export_send_csv(string $filename, array $headers, array $rows): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, $headers, ';');

    foreach ($rows as $row) {
        fputcsv($out, $row, ';');
    }

    fclose($out);
    exit;
}

function export_lista_csv(array $funcionarios): void
{
    $rows = [];
    foreach ($funcionarios as $funcionario) {
        $rows[] = [
            $funcionario['numero_mecanografico'] ?: '',
            $funcionario['nome'] ?? '',
            $funcionario['email'] ?? '',
            $funcionario['telefone'] ?? '',
            $funcionario['funcao'] ?? '',
            $funcionario['equipa_nome'] ?? '',
            $funcionario['tipo_contrato'] ?? '',
            export_format_date($funcionario['data_admissao'] ?? null),
            $funcionario['carga_horaria_semanal'] ?? '',
            ucfirst((string) ($funcionario['estado'] ?? '')),
            $funcionario['codigo_biometrico'] ?? '',
        ];
    }

    export_send_csv(
        'csnsa_funcionarios_' . date('Y-m-d_His') . '.csv',
        [
            'N. mecanografico',
            'Nome',
            'Email',
            'Telefone',
            'Funcao',
            'Equipa',
            'Tipo contrato',
            'Data admissao',
            'Carga horaria semanal',
            'Estado',
            'Codigo biometrico',
        ],
        $rows
    );
}

function export_picagens_csv(mysqli $conn, array $funcionarios, array $periodo): void
{
    require_once __DIR__ . '/registos_ponto_relatorio.php';

    $rows = [];
    foreach ($funcionarios as $funcionario) {
        $funcionarioId = (int) ($funcionario['id'] ?? 0);
        if ($funcionarioId <= 0) {
            continue;
        }

        $picagens = registos_ponto_diarios_por_funcionario($conn, $funcionarioId, $periodo['inicio'], $periodo['fim']);
        if (empty($picagens)) {
            $rows[] = [
                $funcionario['numero_mecanografico'] ?? '',
                $funcionario['nome'] ?? '',
                '',
                '',
                '',
                '',
                'Sem registos',
            ];
            continue;
        }

        foreach ($picagens as $picagem) {
            $rows[] = [
                $funcionario['numero_mecanografico'] ?? '',
                $funcionario['nome'] ?? '',
                export_format_date($picagem['data'] ?? null),
                $picagem['hora_entrada'] ?? '-',
                $picagem['hora_saida'] ?? '-',
                export_picagem_horas($picagem['hora_entrada'] ?? null, $picagem['hora_saida'] ?? null),
                $periodo['label'],
            ];
        }
    }

    export_send_csv(
        'csnsa_picagens_' . date('Y-m-d_His') . '.csv',
        [
            'N. mecanografico',
            'Funcionario',
            'Data',
            'Entrada',
            'Saida',
            'Horas',
            'Periodo',
        ],
        $rows
    );
}

function export_picagem_horas(?string $entrada, ?string $saida): string
{
    if ($entrada === null || $saida === null || $entrada === '-' || $saida === '-') {
        return '-';
    }

    $inicio = strtotime('1970-01-01 ' . $entrada);
    $fim = strtotime('1970-01-01 ' . $saida);
    if ($inicio === false || $fim === false || $fim < $inicio) {
        return '-';
    }

    $minutos = (int) round(($fim - $inicio) / 60);
    $horas = intdiv($minutos, 60);
    $mins = $minutos % 60;

    return sprintf('%02d:%02d', $horas, $mins);
}

function export_lista_pdf(array $funcionarios): void
{
    $tableWidths = [16, 38, 40, 24, 30, 28, 24, 22, 14, 18];

    $pdf = new CSNSAFuncionariosListaPDF('L', 'mm', 'A4');
    $pdf->reportTitle = 'Lista de Funcionarios';
    $pdf->reportSubtitle = 'Registo completo de colaboradores';
    $pdf->totalRecords = count($funcionarios);
    $pdf->bodyContentWidth = (float) array_sum($tableWidths);
    $pdf->SetTitle('CSNSA - Lista de Funcionarios');
    $pdf->SetAuthor('CSNSA');
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->drawEmployeeTable($funcionarios);
    $pdf->Output('I', 'csnsa_funcionarios_' . date('Y-m-d') . '.pdf');
    exit;
}

function export_picagens_pdf(mysqli $conn, array $funcionarios, array $periodo): void
{
    require_once __DIR__ . '/registos_ponto_relatorio.php';

    $pdf = new CSNSAFuncionariosPicagensPDF('P', 'mm', 'A4');
    $pdf->reportTitle = 'Relatorio de Picagens';
    $pdf->reportSubtitle = $periodo['label'] . ' (' . export_format_date($periodo['inicio']) . ' a ' . export_format_date($periodo['fim']) . ')';
    $pdf->totalRecords = count($funcionarios);
    $pdf->bodyContentWidth = 190;
    $pdf->SetTitle('CSNSA - Relatorio de Picagens');
    $pdf->SetAuthor('CSNSA');
    $pdf->AliasNbPages();

    foreach ($funcionarios as $funcionario) {
        $funcionarioId = (int) ($funcionario['id'] ?? 0);
        if ($funcionarioId <= 0) {
            continue;
        }

        $picagens = registos_ponto_diarios_por_funcionario($conn, $funcionarioId, $periodo['inicio'], $periodo['fim']);
        $pdf->AddPage();
        $pdf->drawEmployeePunchSection($funcionario, $picagens, $periodo);
    }

    if ($pdf->PageNo() === 0) {
        $pdf->AddPage();
        $pdf->drawEmptyReport();
    }

    $pdf->Output('I', 'csnsa_picagens_' . date('Y-m-d') . '.pdf');
    exit;
}

class CSNSAExportPdfBase extends FPDF
{
    public string $reportTitle = 'CSNSA';
    public string $reportSubtitle = '';
    public int $totalRecords = 0;
    public float $bodyContentWidth = 0;

    protected array $currentTableWidths = [];
    protected array $brandBlue = [27, 104, 255];
    protected array $brandDark = [31, 41, 55];
    protected array $headerFill = [27, 104, 255];
    protected array $rowFill = [245, 247, 250];
    protected array $gridColor = [209, 213, 219];

    protected function bodyBlockWidth(): float
    {
        if ($this->bodyContentWidth > 0) {
            return $this->bodyContentWidth;
        }

        if (!empty($this->currentTableWidths)) {
            return (float) array_sum($this->currentTableWidths);
        }

        return $this->GetPageWidth() - 24;
    }

    protected function setBodyX(?float $contentWidth = null): void
    {
        $width = $contentWidth ?? $this->bodyBlockWidth();
        $this->SetX(($this->GetPageWidth() - $width) / 2);
    }

    public function Header(): void
    {
        $this->SetFillColor(...$this->brandBlue);
        $this->Rect(0, 0, $this->GetPageWidth(), 24, 'F');

        $this->SetXY(12, 7);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(30, 8, export_pdf_text('CSNSA'), 0, 0, 'L');

        $this->SetFont('Arial', '', 9);
        $this->Cell(0, 8, export_pdf_text('Gestao de RH e Assiduidade'), 0, 1, 'R');

        $contentWidth = $this->bodyBlockWidth();
        $this->SetXY(($this->GetPageWidth() - $contentWidth) / 2, 30);
        $this->SetTextColor(...$this->brandDark);
        $this->SetFont('Arial', 'B', 14);
        $this->Cell($contentWidth, 7, export_pdf_text($this->reportTitle), 0, 1, 'C');

        if ($this->reportSubtitle !== '') {
            $this->setBodyX($contentWidth);
            $this->SetFont('Arial', '', 10);
            $this->SetTextColor(90, 98, 110);
            $this->Cell($contentWidth, 5, export_pdf_text($this->reportSubtitle), 0, 1, 'C');
        }

        $meta = 'Emitido em ' . date('d/m/Y H:i');
        if ($this->totalRecords > 0) {
            $meta .= '  |  Total: ' . $this->totalRecords;
        }

        $this->setBodyX($contentWidth);
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(120, 128, 140);
        $this->Cell($contentWidth, 5, export_pdf_text($meta), 0, 1, 'C');
        $this->Ln(2);
    }

    public function Footer(): void
    {
        $this->SetY(-12);
        $this->SetDrawColor(...$this->gridColor);
        $this->Line(10, $this->GetY(), $this->GetPageWidth() - 10, $this->GetY());
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(120, 128, 140);
        $this->Cell(0, 8, export_pdf_text('CSNSA  |  Pagina ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    protected function drawTableHeader(array $headers, array $widths, float $height = 8): void
    {
        $this->currentTableWidths = $widths;
        $this->setBodyX((float) array_sum($widths));

        $this->SetFillColor(...$this->headerFill);
        $this->SetTextColor(255, 255, 255);
        $this->SetDrawColor(...$this->gridColor);
        $this->SetFont('Arial', 'B', 8);

        foreach ($headers as $index => $label) {
            $this->Cell($widths[$index], $height, export_pdf_text($label), 1, 0, 'C', true);
        }
        $this->Ln();
    }

    protected function drawTableRow(array $cells, array $widths, array $aligns, float $height = 6.5, bool $fill = false): void
    {
        $this->currentTableWidths = $widths;
        $this->setBodyX((float) array_sum($widths));

        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(...$this->brandDark);
        $this->SetDrawColor(...$this->gridColor);

        if ($fill) {
            $this->SetFillColor(...$this->rowFill);
        }

        foreach ($cells as $index => $value) {
            $align = $aligns[$index] ?? 'L';
            $this->Cell($widths[$index], $height, export_pdf_text((string) $value), 1, 0, $align, $fill);
        }
        $this->Ln();
    }
}

class CSNSAFuncionariosListaPDF extends CSNSAExportPdfBase
{
    public function drawEmployeeTable(array $funcionarios): void
    {
        $headers = ['N. Mec.', 'Nome', 'Email', 'Telefone', 'Funcao', 'Equipa', 'Contrato', 'Admissao', 'Carga', 'Estado'];
        $widths = [16, 38, 40, 24, 30, 28, 24, 22, 14, 18];
        $aligns = ['C', 'L', 'L', 'L', 'L', 'L', 'L', 'C', 'C', 'C'];

        $this->drawTableHeader($headers, $widths);

        if (empty($funcionarios)) {
            $this->setBodyX((float) array_sum($widths));
            $this->SetFont('Arial', 'I', 9);
            $this->Cell(array_sum($widths), 10, export_pdf_text('Nenhum funcionario encontrado.'), 1, 1, 'C');
            return;
        }

        $fill = false;
        foreach ($funcionarios as $funcionario) {
            $this->drawTableRow([
                $funcionario['numero_mecanografico'] ?: '-',
                $funcionario['nome'] ?? '-',
                $funcionario['email'] ?: '-',
                $funcionario['telefone'] ?: '-',
                $funcionario['funcao'] ?: '-',
                $funcionario['equipa_nome'] ?: '-',
                $funcionario['tipo_contrato'] ?: '-',
                export_format_date($funcionario['data_admissao'] ?? null),
                ($funcionario['carga_horaria_semanal'] ?? '-') . ' h',
                ucfirst((string) ($funcionario['estado'] ?? '-')),
            ], $widths, $aligns, 6.5, $fill);

            $fill = !$fill;
        }
    }
}

class CSNSAFuncionariosPicagensPDF extends CSNSAExportPdfBase
{
    public function drawEmployeePunchSection(array $funcionario, array $picagens, array $periodo): void
    {
        $contentWidth = $this->bodyContentWidth > 0 ? $this->bodyContentWidth : 190;

        $this->SetFillColor(...$this->rowFill);
        $this->SetDrawColor(...$this->gridColor);
        $this->SetTextColor(...$this->brandDark);

        $this->setBodyX($contentWidth);
        $this->SetFont('Arial', 'B', 11);
        $this->Cell($contentWidth, 8, export_pdf_text($funcionario['nome'] ?? 'Funcionario'), 0, 1, 'C');

        $this->setBodyX($contentWidth);
        $this->SetFont('Arial', '', 9);
        $info = sprintf(
            'N. mec.: %s   |   Funcao: %s   |   Equipa: %s',
            $funcionario['numero_mecanografico'] ?: '-',
            $funcionario['funcao'] ?: '-',
            $funcionario['equipa_nome'] ?: '-'
        );
        $this->Cell($contentWidth, 5, export_pdf_text($info), 0, 1, 'C');
        $this->Ln(2);

        $headers = ['Data', 'Entrada', 'Saida', 'Horas trabalhadas'];
        $widths = [45, 45, 45, 55];
        $aligns = ['C', 'C', 'C', 'C'];
        $this->drawTableHeader($headers, $widths, 7);

        if (empty($picagens)) {
            $this->setBodyX((float) array_sum($widths));
            $this->SetFont('Arial', 'I', 9);
            $this->Cell(array_sum($widths), 8, export_pdf_text('Sem registos de ponto neste periodo.'), 1, 1, 'C');
            return;
        }

        $fill = false;
        foreach ($picagens as $picagem) {
            $this->drawTableRow([
                export_format_date($picagem['data'] ?? null),
                $picagem['hora_entrada'] ?? '-',
                $picagem['hora_saida'] ?? '-',
                export_picagem_horas($picagem['hora_entrada'] ?? null, $picagem['hora_saida'] ?? null),
            ], $widths, $aligns, 7, $fill);
            $fill = !$fill;
        }
    }

    public function drawEmptyReport(): void
    {
        $this->SetFont('Arial', '', 11);
        $this->Cell(0, 10, export_pdf_text('Nenhum funcionario selecionado para exportar.'), 0, 1, 'C');
    }
}
