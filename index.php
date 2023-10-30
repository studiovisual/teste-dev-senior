<?php

define('URL', trim(site_url(), '/').'/');

define('URL_TEMPLATE', get_template_directory_uri().'/');

define('URLS', array(
    'template' => URL_TEMPLATE,
    'css'      => URL_TEMPLATE.'css/',
    'fonts'    => URL_TEMPLATE.'fonts/',
    'imagens'  => URL_TEMPLATE.'imagens/',
    'js'       => URL_TEMPLATE.'js/',
));

/*
** Validar campos do envio no post
** ==============================================*/
function validarCampos($campos_enviados, $campos_validar, $exit = true, $cadastroInternacional = false) {
    $campos = array();

    if(is_array($campos_validar)) {
        foreach ($campos_validar as $key => $value) {
            if($cadastroInternacional && $value == "cpf"){
                continue;
            }
            if($cadastroInternacional && $value == "logradouro"){
                continue;
            }
            if(!isset($campos_enviados[$value]) || empty(trim($campos_enviados[$value]))) {
                $campos[$value] = $value;
            }
        }
    }

    if(!empty($campos)) {
        if($exit) {
            wp_die(wp_send_json(array('sucesso' => false, 'campos' => $campos, 'mensagem' => 'Preencha todos os campos obrigatórios')));
        }
        return array('sucesso' => false, 'campos' => $campos, 'mensagem' => 'Preencha todos os campos obrigatórios');
    }

    return true;
}

function gerarNomeUsuarioUnico($nomeUsuario) {

    $nomeUsuario = sanitize_title($nomeUsuario);

    static $i;
    if(null === $i) {
        $i = 2;
    } else {
        $i++;
    }

    if(!username_exists($nomeUsuario)) {
        return $nomeUsuario;
    }

    $novoNomeUsuario = sprintf('%s-%s', $nomeUsuario, $i);

    if (!username_exists($novoNomeUsuario)) {
        return $novoNomeUsuario;
    } else {
        return call_user_func(__FUNCTION__, $nomeUsuario);
    }
}

function uploadArquivo($arquivo) {

    $dadosArquivo = pathinfo($arquivo['name']);

    $nome = sanitize_title(removeAscentos($dadosArquivo['filename']));

    // upload
    $upload = wp_upload_bits($nome.'.'.$dadosArquivo['extension'], null, file_get_contents($arquivo['tmp_name']));

    if($upload['error'] === false) {
        $wp_upload_dir = wp_upload_dir();

        $attachmentData = array(
            'guid'           => $wp_upload_dir['url'] . '/' . $nome.'.'.$dadosArquivo['extension'],
            'post_mime_type' => $upload['type'],
            'post_title'     => wp_strip_all_tags($dadosArquivo['filename']),
            'post_content'   => '',
            'post_status'    => 'inherit'
        );

        // Insere o anexo
        $attachmentId = wp_insert_attachment($attachmentData, $upload['file']);

        // Gera os metadados (no insert não insere)
        $attach_data = wp_generate_attachment_metadata($attachmentId, $upload['file']);

        // Caso não tenha erro, atualiza os metadados
        if (!is_wp_error($attachmentId)) {
            wp_update_attachment_metadata( $attachmentId, $attach_data );
        }

        if(is_numeric($attachmentId)) {
            return [
                'error' => false,
                'id' => $attachmentId,
                'filename' => $nome,
                'extension' => $dadosArquivo['extension'],
                'size' => $arquivo['size'],
                'type' => $arquivo['type'],
                'url' => $upload['url'],
                'file' => $upload['file']
            ];
        }
    }

    return $upload;
}

function overlay($conteudo, $extraParams = []) {
    $overlayId = uniqid();
    $overlayHtml = overlayHtml('', $overlayId);

    $retorno = array_merge([
        'sucesso' => true,
        'overlay' => true,
        'overlayId' => $overlayId,
        'conteudo' => html($overlayHtml['inicio'].$conteudo.$overlayHtml['fim'])
    ], $extraParams);

    return $retorno;
}

function overlayHtml($param = '', $id = false) {
    if(!$id) {
        $id = uniqid();
    }
    return array(
    'inicio' => '
    <div class="overlay '.$param.'" tabindex data-overlay-container data-overlay-id="'.$id.'" data-overlay-container-id="'.$param.'">
        <div class="overlay-sub margin-auto min-w-0">
            <div class="overlay-sub-2 padding-15 border-box">
                <div class="overlay-box relative">
                    <div class="x-box">
                        <div class="x box-shadow" data-close-overlay="'.$id.'"></div>
                    </div>
                    <div data-overlay-conteudo class="overlay-content">',
                        // Conteudo
                    'fim' => '
                    </div>
                </div>
            </div>
        </div>
    </div>',
    'overlayId' => $id
    );
}

function html($html) {
    if(is_array($html)) {
        $html['conteudo'] = trim(preg_replace('/>\s+<(?!\/textarea)/', '> <', $html['conteudo']));
    } else {
        $html = trim(preg_replace('/>\s+<(?!\/textarea)/', '> <', $html));
    }
    return $html;
}

function cadastroRealizadoComSucessoHtml() {
    $retorno = '';

    $avisoId = 'data-box-aviso-id-remover';

    $retorno .= '
    <div class="align-center overlay-box-default" '.$avisoId.'>
        <div class="bg-2 color-white padding-30 border-radius-5">
            <img src="'.URLS['imagens'].'logo-branco-alt.png" alt="logo" class="table margin-auto w-100" />
            <h3 class="fs-titulo margin-b-0">Cadastro realizado com sucesso!</h3>
            <div>Acesse agora a plataforma Vetsapiens</div>
        </div>
        <div class="margin-t-30">
            <a href="'.URL.'" class="btn btn-default">Ir para página inicial</a>
        </div>
    </div>
    <script>
        fbq("track", "Lead");
    </script>
    ';

    $retorno .= desativarCloseOverlay($avisoId);

    return $retorno;
}

function desativarCloseOverlay($avisoId) {
    $retorno = '';

    $retorno .= '
    <script>
        var container = jQuery("['.$avisoId.']").parents("[data-overlay-container]");
        container.removeAttr("tabindex");
        var btnClose = container.find("[data-close-overlay]");
        btnClose.removeAttr("data-close-overlay");
        jQuery("body").on("click", btnClose, function(){
            location.reload();
        });
    </script>
    ';

    return $retorno;
}


/*
* Cadastrar usuário
* ============================================================ */
function cadastrarUsuario($dados, $arquivos) {
    // definir a chave secreta
    $secret = "LpH4p8zwXo";

    if (empty($_POST["g-recaptcha-response"])) {
        wp_die(wp_send_json([
            'sucesso' => false,
            'mensagem' => 'Você precisa preencher o Recaptcha',
        ]));
    }

    $verifyResponse = file_get_contents('https://www.google.com/recaptcha/api/siteverify?secret='.$secret.'&response='.$_POST['g-recaptcha-response']);

    if (!$verifyResponse['success']) {
        wp_die(wp_send_json([
            'sucesso' => false,
            'mensagem' => 'Captcha inválido',
        ]));
    }

    // deu tudo certo?
    if ($verifyResponse['success']) {
        // Remove todas as tags HTML de qualquer campo
        $cadastroInternacional = false;
        foreach ($dados as $key => $value) {
            $dados[$key] = strip_tags(trim($value));
        }

        if (isset($_POST['cadastro_internacional'])) {
            $cadastroInternacional = true;
        }

        validarCampos($dados, [
            'nome', 'sobrenome', 'email', 'cpf', 'celular', 'senha', 'confirmar_senha', 'pais', 'cep',
            'logradouro', 'aceitar_termos', 'tipo_conta',
            'genero', 'confirmar_email'
        ], true, $cadastroInternacional);

        // Valida email
        if(!validarEmail($dados['email'])) {
            wp_die(wp_send_json([
                'sucesso' => false,
                'mensagem' => 'O e-mail inserido é inválido',
                'campos' => [
                    'email' => 'E-mail inválido'
                ]
            ]));
        }

        // Validar emails iguais
        if($dados['email'] != $dados['confirmar_email']) {
            wp_die(wp_send_json([
                'sucesso' => false,
                'mensagem' => 'O e-mail de confirmação e o e-mail estão diferentes',
                'campos' => [
                    'email' => 'E-mail e confirmação de e-mail',
                    'confirmar_email' => 'E-mail e confirmação de e-mail',
                ]
            ]));
        }

        // Validar CPF
        if(!validarCPF($dados['cpf'], $cadastroInternacional)) {
            wp_die(wp_send_json([
                'sucesso' => false,
                'mensagem' => 'O CPF inserido é inválido',
                'campos' => [
                    'cpf' => 'CPF inválido'
                ]
            ]));
        }

        // Validar senha
        if($dados['senha'] != $dados['confirmar_senha']) {
            wp_die(wp_send_json([
                'sucesso' => false,
                'mensagem' => 'A senha e confirmação da senha estão diferentes',
                'campos' => [
                    'senha' => 'Senha e confirmação de senha',
                    'confirmar_senha' => 'Senha e confirmação de senha',
                ]
            ]));
        }

        // Verificar se e-mail já existe
        $emailExistente = get_user_by('email', $dados['email']);

        if($emailExistente !== false) {
            wp_die(wp_send_json([
                'sucesso' => false,
                'mensagem' => 'Este e-mail já foi foi cadastrado!<br />Caso não lembre da sua senha, utilize a opção de recuperação de senha.',
                'campos' => [
                    'email' => 'E-mail já cadastrado',
                    'confirmar_email' => 'E-mail já cadastrado',
                ]
            ]));
        }

        // Validar tipo de usuário, se foi manipulado no front
        if($dados['tipo_conta'] !== 'estudante' && $dados['tipo_conta'] !== 'medico'){
            wp_die(wp_send_json([
                'sucesso' => false,
                'mensagem' => 'Tipo de usuário inválido.',
                'campos' => [
                    'tipo_conta' => 'Tipo de usuário'
                ]
            ]));
        }

        // Verificar se o CPF já foi utilizado
        if(!$cadastroInternacional) {
            $cpfExistente = get_users(
                array(
                    'meta_key' => 'billing_cpf',
                    'meta_value' => $dados['cpf'],
                )
            );
        }

        if(!empty($cpfExistente) && !$cadastroInternacional) {
            wp_die(wp_send_json([
                'sucesso' => false,
                'mensagem' => 'Este CPF já foi foi cadastrado!<br />Caso não lembre da sua senha, utilize a opção de recuperação de senha.',
                'campos' => [
                    'cpf' => 'CPF já cadastrado'
                ]
            ]));
        }

        // Verificar se o CPF já foi utilizado
        if(!$cadastroInternacional) {
            $cpfExistenteSemPontos = get_users(
                array(
                    'meta_key' => 'billing_cpf',
                    'meta_value' => str_replace(array('.', ',', '-'), '', $dados['cpf']),
                )
            );
        }

        if(!empty($cpfExistenteSemPontos) && !$cadastroInternacional) {
            wp_die(wp_send_json([
                'sucesso' => false,
                'mensagem' => 'Este CPF já foi foi cadastrado!<br />Caso não lembre da sua senha, utilize a opção de recuperação de senha.',
                'campos' => [
                    'cpf' => 'CPF já cadastrado'
                ]
            ]));
        }

        $userName = explode('@', $dados['email']);
        $userName = gerarNomeUsuarioUnico($userName[0]);

        $user_id = wp_create_user($userName, $dados['senha'], $dados['email']);

        if(!is_numeric($user_id)) {
            if(isset($user_id->errors['existing_user_login'][0])) {
                wp_die(wp_send_json([
                    'sucesso' => false,
                    'mensagem' => 'Não foi possível realizar o cadastro, tente novamente. Caso já tenha cadastro, utilize a opção de recuperação de senha.',
                    'campos' => [
                        'email' => $user_id->errors['existing_user_login'][0]
                    ]
                ]));
            }
        }

        // Remove todas as tags HTML de qualquer campo
        foreach ($dados as $key => $value) {
            $dados[$key] = strip_tags(trim($value));
        }

        wp_update_user([
            'ID'           => $user_id,
            'role'         => $dados['tipo_conta'],
            'display_name' => $dados['nome'].' '.$dados['sobrenome'],
            'nickname'     => $dados['nome'],
            'first_name'   => $dados['nome'],
            'last_name'    => $dados['sobrenome']
        ]);

        $dadosUsuario = [
            'first_name'   => $dados['nome'],
            'last_name'    => $dados['sobrenome'],
            'cpf'          => $dados['cpf'],
            'birthdate'    => $dados['data_nascimento'],
            'sex'          => $dados['genero'],
            'address_1'    => $dados['logradouro'],
            'address_2'    => $dados['complemento'],
            'number'       => $dados['numero'],
            'neighborhood' => $dados['bairro'],
            'city'         => $dados['cidade'],
            'postcode'     => $dados['cep'],
            'country'      => $dados['pais'],
            'state'        => $dados['estado'],
            'phone'        => $dados['celular'],
            'cellphone'    => $dados['celular'],
        ];

        // Cobrança
        foreach ($dadosUsuario as $key => $value) {
            update_user_meta($user_id, 'billing_'.$key, $value);
            update_user_meta($user_id, 'shipping_'.$key, $value);
        }

        // Campos extras
        update_user_meta($user_id, 'tipo_conta', $dados['tipo_conta']);
        update_user_meta($user_id, 'numero_matricula_ou_crmv', $dados['numero_matricula_ou_crmv']);
        update_user_meta($user_id, 'universidade', $dados['universidade']);
        update_user_meta($user_id, 'cadastro_internacional', $cadastroInternacional);

        // Adiciona foto do usuário
        $idFotoUsuario = uploadArquivo($arquivos['foto_usuario']);
        update_user_meta($user_id, 'foto_usuario', $idFotoUsuario);

        // Adiciona foto da carteirinha
        $idFotoCarteirinha = uploadArquivo($arquivos['foto_carteirinha']);
        update_user_meta($user_id, 'foto_carteirinha', $idFotoCarteirinha);

        $user = get_user_by( 'id', $user_id );

        $opt = get_option( 'personalizacao' );

        // Verifica se está ok e loga o usuário, e envia e-mail
        if( $user ) {
            wp_set_current_user( $user_id, $user->user_login );
            wp_set_auth_cookie( $user_id );


            if ($opt['habilitar-integracao-mailchimp-novos-cadastros']) {
                // Cadastra o usuário no Malchimp
                // [us19] é o mesmo que está a conta do cliente
                // [312ef7fc54] é o ID da lista do cliente (utilizar o /lists para visualizar)
                $url = $opt['mailchimp-api-url'] . '/3.0/lists/' . $opt['mailchimp-api-list-id'] . '/members';

                // Data de nascimento invertida
                $dataNascimentoArr = explode('/', $dados['data_nascimento']);
                $dataNascimento = $dataNascimentoArr[2] . '/' . $dataNascimentoArr[1] . '/' . $dataNascimentoArr[0];

                // Tags
                if ($dados['tipo_conta'] == 'medico') {
                    $tipoContaMailchimp = 'Médico';
                } else {
                    $tipoContaMailchimp = 'Estudante';
                }

                // Array de tags
                $tagsMailchimp = [
                    'Cadastros',
                    $tipoContaMailchimp
                ];

                // Dados para cadastrar
                $dadosMailchimp = [
                    'email_address' => $dados['email'],
                    'status' => 'subscribed',
                    'tags' => $tagsMailchimp, // Tag do mailchimp para identificar novos usuários
                    'merge_fields' => [
                        'FNAME' => $dados['nome'],
                        'LNAME' => $dados['sobrenome'],
                        'PHONE' => $dados['celular'],
                        'ADDRESS' => $dados['logradouro'] . ', #' . $dados['numero'] . ' - ' . $dados['bairro'] . ', ' . $dados['cidade'] . ' - ' . $dados['estado'],
                        'BIRTHDAY' => $dataNascimento
                    ]
                ];

                // Token de autorização
                $token = $opt['mailchimp-api-token'];

                // Salvar no Maichimp (no momento, não precisa do retorno)
                $salvarMalchimp = curl(
                    $url,
                    json_encode($dadosMailchimp),
                    $token
                );
            }

            // Envia e-mail de boas vindas

            do_action('enviar_email_cadastro', $user_id, $dados['email'], $dados['nome']);

            if ($opt['habilitar-envio-admin-lista-transmissao']) {
                $msg_pais = '';
                if ($dados['pais'] != "BR") {
                    $msg_pais = '<p>País: <strong>' . $dados['pais'] . '</strong></p>';
                }

                $to = $opt['emails-envio-admin-novo-cadastro-lista-transmissao'];
                $subject = 'empresa - Lista Transmissão';
                $message = '
                Nome: ' . $dados['nome'] . '<br>
                Tipo da conta: ' . $dados['tipo_conta'] . '<br>
                Telefone: ' . $dados['celular'] . '<br>
                Aceita ser contactado pelo whatsapp? ' . $dados['lista_transmissao'] . '<br>
                Cidade/Estado ' . $dados['cidade'] . '/' . $dados['estado'] . '<br>
                ' . $msg_pais;

                $headers = ['Content-Type: text/html; charset=UTF-8'];

                wp_mail($to, $subject, $message, $headers);
            }

            // O envio será feito pelos administradores do empresa através do MailChimp
            // Por esse motivo, os e-mails aqui foram desativados.

            // Envia e-mail confirmando o cadastro para o usuário
            if($opt['habilitar-envio-usuario-novo-cadastro']) {
                wp_mail(
                    $dados['email'],

                    'Seja Bem-vindo(a) ao empresa',
                    '<p><img src="'.$opt['logo-email']['url'].'" alt="logo-empresa" /></p>
                    <p>Olá '.$dados['nome'].', seu cadastro foi efetuado com sucesso!</p>
                    <p>Acesse agora mesmo o <a href="https://empresa.com">empresa.com</a> e aproveite todos os recursos que a nossa plataforma oferece:</p>
                    <br />
                    <p>Em caso de dúvidas, entre em contato conosco através do info@empresa.com.</p>
                    <br />
                    <p>Atenciosamente,</p>
                    <p>Equipe empresa</p>
                    <img src="https://midias-empresa.s3.amazonaws.com/uploads/2020/02/logo-assinatura.jpg" alt="logo-empresa" />
                    ',

                    ['Content-Type: text/html; charset=UTF-8']
                );
            }

            // Envia e-mail confirmando o admin
            if($opt['habilitar-envio-admin-novo-cadastro']) {
                wp_mail(
                    $opt['emails-envio-admin-novo-cadastro'],

                    'empresa - Novo usuário cadastrado',

                    '<p><img src="'.$opt['logo-email']['url'].'" alt="logo-empresa" /></p>
                    <p>Um novo usuário chamado <strong>'.$dados['nome'].'</strong> ('.$dados['email'].'), se cadastrou em empresa.</p>
                    <p>Para visualizar o perfil, editar ou excluir o cadastro acesse a plataforma administrativa.</p>
                    <p>Este é um e-mail automático, não é necessário respondê-lo.</p>
                    <img src="https://midias-empresa.s3.amazonaws.com/uploads/2020/02/logo-assinatura.jpg" alt="logo-empresa" />',

                    ['Content-Type: text/html; charset=UTF-8']
                );
            }

            wp_die(wp_send_json(overlay(cadastroRealizadoComSucessoHtml())));

        } else {
            wp_die(wp_send_json([
                'sucesso' => false,
                'mensagem' => 'Não foi possível recuperar os dados de cadastro, por favor nos informe o erro ocorrido através dos outros meios de contato. Agradecemos a compreensão e colaboração.'
            ]));
        }
    }

}