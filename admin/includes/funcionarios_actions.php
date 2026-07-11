<?php

require_once __DIR__ . '/avatar_helpers.php';

function funcionario_estado_badge($estado)
{
    $estado = strtolower((string) $estado);
    if ($estado === 'ativo') {
        return 'success';
    }
    if ($estado === 'suspenso') {
        return 'warning';
    }
    if ($estado === 'arquivado') {
        return 'dark';
    }
    return 'secondary';
}

function funcionario_campos_unicos(): array
{
    return [
        'email' => 'email',
        'numero_mecanografico' => 'número mecanográfico',
        'pin_ponto' => 'PIN de ponto',
        'codigo_biometrico' => 'código de picagem',
    ];
}

function funcionario_buscar_conflito_unico($conn, string $campo, ?string $valor, int $excluirId = 0): ?array
{
    $campos = funcionario_campos_unicos();
    if (!isset($campos[$campo]) || $valor === null || $valor === '') {
        return null;
    }

    $sql = "SELECT id, nome, estado FROM funcionarios WHERE {$campo} = ?";
    if ($excluirId > 0) {
        $sql .= ' AND id <> ?';
    }
    $sql .= ' LIMIT 1';

    $stmt = mysqli_prepare($conn, $sql);
    if ($excluirId > 0) {
        mysqli_stmt_bind_param($stmt, 'si', $valor, $excluirId);
    } else {
        mysqli_stmt_bind_param($stmt, 's', $valor);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return $row ?: null;
}

function funcionario_validar_campos_unicos($conn, array $valores, int $excluirId = 0): ?string
{
    foreach (funcionario_campos_unicos() as $campo => $rotulo) {
        $valor = $valores[$campo] ?? null;
        $conflito = funcionario_buscar_conflito_unico($conn, $campo, $valor, $excluirId);
        if (!$conflito) {
            continue;
        }

        $nome = (string) ($conflito['nome'] ?? 'outro funcionário');
        if (($conflito['estado'] ?? '') === 'arquivado') {
            return "Já existe um funcionário arquivado ({$nome}) com este {$rotulo}. Vá a Ver arquivados para apagar ou restaurar, ou use outro valor.";
        }

        return "Já existe outro funcionário ({$nome}) com este {$rotulo}.";
    }

    return null;
}

function funcionario_mensagem_erro_sql($conn): string
{
    $errno = mysqli_errno($conn);
    $error = (string) mysqli_error($conn);

    if ($errno === 1062) {
        foreach (funcionario_campos_unicos() as $campo => $rotulo) {
            if (stripos($error, $campo) !== false) {
                return "Já existe outro funcionário com este {$rotulo}.";
            }
        }

        return 'Já existe outro funcionário com os mesmos dados únicos (email, número mecanográfico, PIN ou código de picagem).';
    }

    return 'Não foi possível guardar o funcionário. Verifique os dados e tente novamente.';
}

function funcionario_normalizar_telefone(?string $telefone): ?string
{
    $telefone = trim((string) $telefone);
    if ($telefone === '') {
        return null;
    }

    if (!function_exists('normalize_phone')) {
        require_once __DIR__ . '/auth.php';
    }

    return normalize_phone($telefone);
}

function funcionario_validar_telefone(?string $telefone, bool $obrigatorio = false): ?string
{
    $telefone = trim((string) $telefone);
    if ($telefone === '') {
        return $obrigatorio ? 'Indique o número de telefone.' : null;
    }

    $normalizado = funcionario_normalizar_telefone($telefone);
    $digitos = preg_replace('/\D+/', '', (string) $normalizado);
    if (strlen($digitos) < 8 || strlen($digitos) > 15) {
        return 'Telefone inválido. Introduza um número real com pelo menos 8 dígitos.';
    }

    return null;
}

function funcionario_data_nascimento_maxima(): string
{
    return (new DateTime('today'))->modify('-18 years')->format('Y-m-d');
}

function funcionario_validar_data_nascimento(?string $dataNascimento, bool $obrigatorio = false): ?string
{
    $dataNascimento = trim((string) $dataNascimento);
    if ($dataNascimento === '') {
        return $obrigatorio ? 'Indique a data de nascimento.' : null;
    }

    $nascimento = DateTime::createFromFormat('Y-m-d', $dataNascimento);
    if (!$nascimento || $nascimento->format('Y-m-d') !== $dataNascimento) {
        return 'Data de nascimento inválida.';
    }

    $hoje = new DateTime('today');
    if ($nascimento > $hoje) {
        return 'A data de nascimento não pode ser no futuro.';
    }

    if ($nascimento->format('Y-m-d') > funcionario_data_nascimento_maxima()) {
        return 'O funcionário tem de ter pelo menos 18 anos.';
    }

    return null;
}

function funcionario_tem_dependencias($conn, $funcionarioId)
{
    $checks = [
        ['registos_ponto', 'funcionario_id'],
        ['horarios_turno', 'funcionario_id'],
        ['banco_horas', 'funcionario_id'],
        ['escala_funcionarios', 'funcionario_id'],
        ['ferias_ausencias', 'funcionario_id'],
    ];

    foreach ($checks as [$table, $column]) {
        if (!fe_table_exists($conn, $table) || !fe_column_exists($conn, $table, $column)) {
            continue;
        }

        $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM $table WHERE $column = ?");
        mysqli_stmt_bind_param($stmt, 'i', $funcionarioId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ((int) ($row['total'] ?? 0) > 0) {
            return true;
        }
    }

    return false;
}

function funcionarios_missing_tables($conn): array
{
    $missing = [];
    if (!fe_table_exists($conn, 'funcionarios')) {
        $missing[] = 'funcionarios';
    }
    return $missing;
}

function funcionarios_tem_equipas($conn): bool
{
    return fe_table_exists($conn, 'equipas') && fe_column_exists($conn, 'funcionarios', 'equipa_id');
}

function funcionarios_load_equipas($conn): array
{
    $equipas = [];
    if (!funcionarios_tem_equipas($conn)) {
        return $equipas;
    }

    $stmt = mysqli_prepare($conn, 'SELECT id, nome, codigo, descricao FROM equipas WHERE ativo = 1 ORDER BY nome ASC');
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $equipas[] = $row;
    }
    mysqli_stmt_close($stmt);

    return $equipas;
}

function funcionarios_listagem_redirect_query(): array
{
    if (($_POST['listagem'] ?? '') === 'arquivados') {
        return ['arquivados' => '1'];
    }

    return [];
}

function funcionarios_load_list($conn, bool $somenteArquivados = false): array
{
    if (!empty(funcionarios_missing_tables($conn))) {
        return [];
    }

    $temEquipas = funcionarios_tem_equipas($conn);
    $selectEquipa = $temEquipas ? 'eq.nome AS equipa_nome' : 'NULL AS equipa_nome';
    $joinEquipa = $temEquipas ? 'LEFT JOIN equipas eq ON eq.id = f.equipa_id' : '';
    $filtroArquivados = $somenteArquivados
        ? " WHERE f.estado = 'arquivado'"
        : " WHERE f.estado <> 'arquivado'";

    $sql = "SELECT f.*, $selectEquipa
        FROM funcionarios f
        $joinEquipa
        $filtroArquivados
        ORDER BY FIELD(f.estado, 'ativo', 'suspenso', 'inativo', 'arquivado'), f.nome ASC";

    $funcionarios = [];
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $funcionarios[] = $row;
    }
    mysqli_stmt_close($stmt);

    return $funcionarios;
}

function process_funcionarios_post($conn, string $redirectPage): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    $acao = $_POST['acao'] ?? '';
    $redirectQuery = funcionarios_listagem_redirect_query();
    $redirect = static function (string $type, string $message, array $extra = []) use ($redirectPage, $redirectQuery): void {
        admin_redirect_msg($redirectPage, $type, $message, array_merge($redirectQuery, $extra));
    };
    if (!in_array($acao, ['criar', 'editar', 'remover', 'restaurar', 'eliminar_permanente'], true)) {
        return;
    }

    if (!empty(funcionarios_missing_tables($conn))) {
        admin_redirect_msg($redirectPage, 'danger', 'Importe o ficheiro csnsa.sql na base de dados antes de gerir funcionários.');
    }

    if (function_exists('site_run_migrations')) {
        site_run_migrations($conn);
    }

    $temEquipas = funcionarios_tem_equipas($conn);
    $temDataNascimento = fe_column_exists($conn, 'funcionarios', 'data_nascimento');
    $temDataDiuturnidade = fe_column_exists($conn, 'funcionarios', 'data_diuturnidade');

    if ($acao === 'criar' || $acao === 'editar') {
        $id = (int) ($_POST['id'] ?? 0);
        $nome = get_post_value('nome');
        $numeroMecanografico = nullable_text($_POST['numero_mecanografico'] ?? '');
        $email = nullable_text($_POST['email'] ?? '');
        $telefone = nullable_text($_POST['telefone'] ?? '');
        $funcao = nullable_text($_POST['funcao'] ?? '');
        $categoria = nullable_text($_POST['categoria_profissional'] ?? '');
        $setorId = nullable_int($_POST['setor_id'] ?? '');
        $equipaId = $temEquipas ? nullable_int($_POST['equipa_id'] ?? '') : null;
        $dataAdmissao = nullable_date($_POST['data_admissao'] ?? '');
        $dataNascimento = $temDataNascimento ? nullable_date($_POST['data_nascimento'] ?? '') : null;
        $dataDiuturnidade = $temDataDiuturnidade ? nullable_date($_POST['data_diuturnidade'] ?? '') : null;
        $dataCessacao = nullable_date($_POST['data_cessacao'] ?? '');
        $tipoContrato = nullable_text($_POST['tipo_contrato'] ?? '');
        $cargaHoraria = (float) str_replace(',', '.', $_POST['carga_horaria_semanal'] ?? '40');
        $pinPonto = nullable_text($_POST['pin_ponto'] ?? '');
        $codigoBiometrico = nullable_text($_POST['codigo_biometrico'] ?? '');
        $estado = strtolower(get_post_value('estado') ?: 'ativo');
        $observacoes = nullable_text($_POST['observacoes'] ?? '');
        $servico = nullable_text($_POST['servico'] ?? '');
        $horario = nullable_text($_POST['horario'] ?? '');
        $endereco = nullable_text($_POST['endereco'] ?? '');
        $notas = nullable_text($_POST['notas'] ?? '');

        if ($nome === '') {
            $redirect('danger', 'Preencha o nome do funcionário.');
        }

        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $redirect('danger', 'Introduza um email válido.');
        }

        $erroTelefone = funcionario_validar_telefone($telefone, false);
        if ($erroTelefone !== null) {
            $redirect('danger', $erroTelefone);
        }
        $telefone = funcionario_normalizar_telefone($telefone);

        if ($temDataNascimento) {
            $erroNascimento = funcionario_validar_data_nascimento($dataNascimento, $acao === 'criar');
            if ($erroNascimento !== null) {
                $redirect('danger', $erroNascimento);
            }
        }

        if ($cargaHoraria <= 0) {
            $redirect('danger', 'A carga horaria semanal deve ser superior a zero.');
        }

        if (!in_array($estado, ['ativo', 'suspenso', 'inativo', 'arquivado'], true)) {
            $estado = 'ativo';
        }

        $fotoPath = null;
        try {
            $fotoPath = avatar_save_image_upload($_FILES['foto'] ?? [], 'funcionarios');
        } catch (RuntimeException $e) {
            $redirect('danger', $e->getMessage());
        }

        $erroUnico = funcionario_validar_campos_unicos($conn, [
            'email' => $email,
            'numero_mecanografico' => $numeroMecanografico,
            'pin_ponto' => $pinPonto,
            'codigo_biometrico' => $codigoBiometrico,
        ], $acao === 'editar' ? $id : 0);
        if ($erroUnico !== null) {
            $redirect('danger', $erroUnico);
        }

        try {
            if ($acao === 'criar') {
                if ($temDataNascimento && $temDataDiuturnidade) {
                    $stmt = mysqli_prepare($conn, "INSERT INTO funcionarios
                        (setor_id, equipa_id, numero_mecanografico, nome, email, telefone, funcao, categoria_profissional,
                         servico, horario, endereco, notas, foto, data_admissao, data_nascimento, data_diuturnidade, data_cessacao,
                         tipo_contrato, carga_horaria_semanal, pin_ponto, codigo_biometrico, estado, observacoes)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    mysqli_stmt_bind_param(
                        $stmt,
                        'iissssssssssssssssdssss',
                        $setorId,
                        $equipaId,
                        $numeroMecanografico,
                        $nome,
                        $email,
                        $telefone,
                        $funcao,
                        $categoria,
                        $servico,
                        $horario,
                        $endereco,
                        $notas,
                        $fotoPath,
                        $dataAdmissao,
                        $dataNascimento,
                        $dataDiuturnidade,
                        $dataCessacao,
                        $tipoContrato,
                        $cargaHoraria,
                        $pinPonto,
                        $codigoBiometrico,
                        $estado,
                        $observacoes
                    );
                } else {
                    $stmt = mysqli_prepare($conn, "INSERT INTO funcionarios
                        (setor_id, equipa_id, numero_mecanografico, nome, email, telefone, funcao, categoria_profissional,
                         servico, horario, endereco, notas, foto, data_admissao, data_cessacao, tipo_contrato, carga_horaria_semanal,
                         pin_ponto, codigo_biometrico, estado, observacoes)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    mysqli_stmt_bind_param(
                        $stmt,
                        'iissssssssssssssdssss',
                        $setorId,
                        $equipaId,
                        $numeroMecanografico,
                        $nome,
                        $email,
                        $telefone,
                        $funcao,
                        $categoria,
                        $servico,
                        $horario,
                        $endereco,
                        $notas,
                        $fotoPath,
                        $dataAdmissao,
                        $dataCessacao,
                        $tipoContrato,
                        $cargaHoraria,
                        $pinPonto,
                        $codigoBiometrico,
                        $estado,
                        $observacoes
                    );
                }
                if (!mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_close($stmt);
                    $redirect('danger', funcionario_mensagem_erro_sql($conn));
                }
                mysqli_stmt_close($stmt);

                $redirect('success', 'Funcionário criado com sucesso.');
            }

            if ($id <= 0) {
                $redirect('danger', 'Funcionário inválido.');
            }

            if ($fotoPath !== null) {
                if ($temDataNascimento && $temDataDiuturnidade) {
                    $stmt = mysqli_prepare($conn, "UPDATE funcionarios SET
                            setor_id = ?, equipa_id = ?, numero_mecanografico = ?, nome = ?, email = ?, telefone = ?,
                            funcao = ?, categoria_profissional = ?, servico = ?, horario = ?, endereco = ?, notas = ?, foto = ?,
                            data_admissao = ?, data_nascimento = ?, data_diuturnidade = ?, data_cessacao = ?, tipo_contrato = ?,
                            carga_horaria_semanal = ?, pin_ponto = ?, codigo_biometrico = ?, estado = ?, observacoes = ?
                        WHERE id = ?");
                    mysqli_stmt_bind_param(
                        $stmt,
                        'iissssssssssssssssdssssi',
                        $setorId,
                        $equipaId,
                        $numeroMecanografico,
                        $nome,
                        $email,
                        $telefone,
                        $funcao,
                        $categoria,
                        $servico,
                        $horario,
                        $endereco,
                        $notas,
                        $fotoPath,
                        $dataAdmissao,
                        $dataNascimento,
                        $dataDiuturnidade,
                        $dataCessacao,
                        $tipoContrato,
                        $cargaHoraria,
                        $pinPonto,
                        $codigoBiometrico,
                        $estado,
                        $observacoes,
                        $id
                    );
                } else {
                    $stmt = mysqli_prepare($conn, "UPDATE funcionarios SET
                            setor_id = ?, equipa_id = ?, numero_mecanografico = ?, nome = ?, email = ?, telefone = ?,
                            funcao = ?, categoria_profissional = ?, servico = ?, horario = ?, endereco = ?, notas = ?, foto = ?,
                            data_admissao = ?, data_cessacao = ?, tipo_contrato = ?, carga_horaria_semanal = ?,
                            pin_ponto = ?, codigo_biometrico = ?, estado = ?, observacoes = ?
                        WHERE id = ?");
                    mysqli_stmt_bind_param(
                        $stmt,
                        'iissssssssssssssdssssi',
                        $setorId,
                        $equipaId,
                        $numeroMecanografico,
                        $nome,
                        $email,
                        $telefone,
                        $funcao,
                        $categoria,
                        $servico,
                        $horario,
                        $endereco,
                        $notas,
                        $fotoPath,
                        $dataAdmissao,
                        $dataCessacao,
                        $tipoContrato,
                        $cargaHoraria,
                        $pinPonto,
                        $codigoBiometrico,
                        $estado,
                        $observacoes,
                        $id
                    );
                }
            } else {
                if ($temDataNascimento && $temDataDiuturnidade) {
                    $stmt = mysqli_prepare($conn, "UPDATE funcionarios SET
                            setor_id = ?, equipa_id = ?, numero_mecanografico = ?, nome = ?, email = ?, telefone = ?,
                            funcao = ?, categoria_profissional = ?, servico = ?, horario = ?, endereco = ?, notas = ?,
                            data_admissao = ?, data_nascimento = ?, data_diuturnidade = ?, data_cessacao = ?, tipo_contrato = ?,
                            carga_horaria_semanal = ?, pin_ponto = ?, codigo_biometrico = ?, estado = ?, observacoes = ?
                        WHERE id = ?");
                    mysqli_stmt_bind_param(
                        $stmt,
                        'iisssssssssssssssdssssi',
                        $setorId,
                        $equipaId,
                        $numeroMecanografico,
                        $nome,
                        $email,
                        $telefone,
                        $funcao,
                        $categoria,
                        $servico,
                        $horario,
                        $endereco,
                        $notas,
                        $dataAdmissao,
                        $dataNascimento,
                        $dataDiuturnidade,
                        $dataCessacao,
                        $tipoContrato,
                        $cargaHoraria,
                        $pinPonto,
                        $codigoBiometrico,
                        $estado,
                        $observacoes,
                        $id
                    );
                } else {
                    $stmt = mysqli_prepare($conn, "UPDATE funcionarios SET
                            setor_id = ?, equipa_id = ?, numero_mecanografico = ?, nome = ?, email = ?, telefone = ?,
                            funcao = ?, categoria_profissional = ?, servico = ?, horario = ?, endereco = ?, notas = ?,
                            data_admissao = ?, data_cessacao = ?, tipo_contrato = ?, carga_horaria_semanal = ?,
                            pin_ponto = ?, codigo_biometrico = ?, estado = ?, observacoes = ?
                        WHERE id = ?");
                    mysqli_stmt_bind_param(
                        $stmt,
                        'iisssssssssssssdssssi',
                        $setorId,
                        $equipaId,
                        $numeroMecanografico,
                        $nome,
                        $email,
                        $telefone,
                        $funcao,
                        $categoria,
                        $servico,
                        $horario,
                        $endereco,
                        $notas,
                        $dataAdmissao,
                        $dataCessacao,
                        $tipoContrato,
                        $cargaHoraria,
                        $pinPonto,
                        $codigoBiometrico,
                        $estado,
                        $observacoes,
                        $id
                    );
                }
            }
            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                $redirect('danger', funcionario_mensagem_erro_sql($conn));
            }
            mysqli_stmt_close($stmt);

            $redirect('success', 'Funcionário atualizado com sucesso.');
        } catch (mysqli_sql_exception $e) {
            $redirect('danger', funcionario_mensagem_erro_sql($conn));
        }
    }

    if ($acao === 'remover') {
        $id = (int) ($_POST['id'] ?? 0);
        $confirmacao = ($_POST['confirmar_arquivar'] ?? '') === '1';

        if ($id <= 0) {
            $redirect('danger', 'Funcionário inválido.');
        }

        if (!$confirmacao) {
            $redirect('danger', 'Confirme que pretende arquivar o funcionário.');
        }

        try {
            $temArquivadoEm = fe_column_exists($conn, 'funcionarios', 'arquivado_em');
            if ($temArquivadoEm) {
                $stmt = mysqli_prepare($conn, "UPDATE funcionarios SET estado = 'arquivado', arquivado_em = NOW() WHERE id = ? AND estado <> 'arquivado'");
            } else {
                $stmt = mysqli_prepare($conn, "UPDATE funcionarios SET estado = 'arquivado' WHERE id = ? AND estado <> 'arquivado'");
            }
            mysqli_stmt_bind_param($stmt, 'i', $id);
            mysqli_stmt_execute($stmt);
            $afetadas = mysqli_stmt_affected_rows($stmt);
            mysqli_stmt_close($stmt);

            if ($afetadas < 1) {
                $redirect('danger', 'Funcionário não encontrado ou já arquivado.');
            }

            $redirect('success', 'Funcionário removido da lista principal. Se precisar, encontra-o em Ver arquivados.');
        } catch (mysqli_sql_exception $e) {
            $redirect('danger', 'Não foi possível remover o funcionário.');
        }
    }

    if ($acao === 'restaurar') {
        $id = (int) ($_POST['id'] ?? 0);

        if ($id <= 0) {
            $redirect('danger', 'Funcionário inválido.');
        }

        try {
            if (fe_column_exists($conn, 'funcionarios', 'arquivado_em')) {
                $stmt = mysqli_prepare($conn, "UPDATE funcionarios SET estado = 'ativo', arquivado_em = NULL WHERE id = ? AND estado = 'arquivado'");
            } else {
                $stmt = mysqli_prepare($conn, "UPDATE funcionarios SET estado = 'ativo' WHERE id = ? AND estado = 'arquivado'");
            }
            mysqli_stmt_bind_param($stmt, 'i', $id);
            mysqli_stmt_execute($stmt);
            $afetadas = mysqli_stmt_affected_rows($stmt);
            mysqli_stmt_close($stmt);

            if ($afetadas < 1) {
                $redirect('danger', 'Funcionário não encontrado ou já não está arquivado.');
            }

            admin_redirect_msg($redirectPage, 'success', 'Funcionário voltou à lista principal.');
        } catch (mysqli_sql_exception $e) {
            $redirect('danger', 'Não foi possível restaurar o funcionário.');
        }
    }

    if ($acao === 'eliminar_permanente') {
        $id = (int) ($_POST['id'] ?? 0);
        $confirmacao = ($_POST['confirmar_eliminar'] ?? '') === '1';

        if ($id <= 0) {
            $redirect('danger', 'Funcionário inválido.');
        }

        if (!$confirmacao) {
            $redirect('danger', 'Confirme que pretende apagar o funcionário definitivamente.');
        }

        try {
            $stmt = mysqli_prepare($conn, "SELECT id, estado FROM funcionarios WHERE id = ? LIMIT 1");
            mysqli_stmt_bind_param($stmt, 'i', $id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $funcionario = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);

            if (!$funcionario) {
                $redirect('danger', 'Funcionário não encontrado.');
            }

            if (($funcionario['estado'] ?? '') !== 'arquivado') {
                $redirect('danger', 'Só pode apagar definitivamente funcionários que já foram removidos da lista.');
            }

            $stmt = mysqli_prepare($conn, 'DELETE FROM funcionarios WHERE id = ? AND estado = ? LIMIT 1');
            $estadoArquivado = 'arquivado';
            mysqli_stmt_bind_param($stmt, 'is', $id, $estadoArquivado);
            mysqli_stmt_execute($stmt);
            $afetadas = mysqli_stmt_affected_rows($stmt);
            mysqli_stmt_close($stmt);

            if ($afetadas < 1) {
                $redirect('danger', 'Não foi possível apagar o funcionário.');
            }

            $redirect('success', 'Funcionário apagado definitivamente do sistema.');
        } catch (mysqli_sql_exception $e) {
            $redirect('danger', 'Não foi possível apagar o funcionário. Pode ter dados ligados que impedem a remoção.');
        }
    }
}
