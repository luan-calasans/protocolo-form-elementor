# Protocolo Elementor

Plugin WordPress que gera, armazena e valida **protocolos numéricos de 18 dígitos** para envios de formulários do **Elementor Pro**, utilizando o sistema nativo de Submissions.

Cada envio habilitado recebe um identificador único, persistido junto à submissão e exibido na mensagem de sucesso do formulário. A validade do protocolo pode ser conferida a qualquer momento no painel administrativo.

---

## Recursos

- Geração automática de protocolo no envio do formulário
- Persistência no banco de Submissions do Elementor Pro (sem tabelas extras)
- Exibição do protocolo na mensagem de sucesso, no frontend
- Validação administrativa com conferência criptográfica (HMAC)
- Configuração por **Form Name** — apenas os formulários listados geram protocolo
- Compatível com submissões já existentes de versões anteriores do plugin

---

## Requisitos

| Item | Versão mínima |
|------|----------------|
| WordPress | 6.0 |
| PHP | 7.4 |
| Elementor Pro | ativo, com a ação **Collect Submissions** nos formulários |

O plugin **não funciona** apenas com o Elementor gratuito: depende das Submissions do Elementor Pro.

---

## Instalação

1. Copie a pasta `protocolo-elementor` para `wp-content/plugins/`.
2. No WordPress, acesse **Plugins** e ative **Protocolo Elementor**.
3. Confirme que o Elementor Pro está ativo.

Se uma versão anterior do plugin já estiver instalada, desative-a antes de ativar esta. As configurações de Form Names são migradas automaticamente.

---

## Configuração

No menu **Protocolos → Configurações**, informe os **Form Names** que devem gerar protocolo, **um por linha**.

O valor precisa coincidir exatamente com o campo **Form Name** do widget de formulário no Elementor (não o título da página).

Em cada formulário habilitado:

1. Abra o widget **Form** no Elementor.
2. Em **Actions After Submit**, mantenha **Collect Submissions** ativo.
3. Confira o **Form Name** e cadastre o mesmo valor nas configurações do plugin.

Somente formulários listados recebem protocolo. Os demais continuam funcionando normalmente, sem identificador extra.

---

## Formato do protocolo

O protocolo tem **18 dígitos numéricos**, sem letras nem separadores:

```
YYYYMMDDHHmmssXXXX
│              └── 4 dígitos de assinatura (HMAC)
└────────────────── 14 dígitos de data/hora (fuso do WordPress)
```

| Parte | Tamanho | Origem |
|-------|---------|--------|
| Data e hora | 14 dígitos | Instantâneo do envio (`YmdHis`) |
| Assinatura | 4 dígitos | HMAC-SHA256 derivado da submissão |

A assinatura considera timestamp, microssegundos, ID da submissão, nome e ID do formulário, ID da página, fingerprint dos campos e um contador de tentativa. O segredo é o salt de autenticação do WordPress (`wp_salt('auth')`).

Em caso de colisão (protocolo já existente), o gerador tenta até 20 variações antes de desistir.

---

## Validação

Em **Protocolos → Validar Protocolo**, informe os 18 dígitos. O plugin:

1. Localiza a submissão correspondente no Elementor.
2. Recalcula a assinatura com os metadados armazenados.
3. Compara o resultado com o protocolo informado (`hash_equals`).

Se for válido, exibe protocolo, nome, e-mail, data, hora, ID da submissão e dados do formulário.

Um protocolo inválido pode indicar digitação incorreta, alteração dos dados da submissão ou uso de um identificador que não foi gerado por este plugin.

---

## Experiência do visitante

Após um envio bem-sucedido em um formulário habilitado, a mensagem de sucesso do Elementor passa a incluir uma linha no formato:

```
Protocolo: 202608261430221847
```

A mensagem original do formulário é preservada; o protocolo é apenas acrescentado.

---

## Estrutura do projeto

```
protocolo-elementor/
├── protocolo-elementor.php          # Bootstrap do plugin
├── assets/js/frontend-protocol.js   # Exibe o protocolo no frontend
└── includes/
    ├── Plugin.php
    ├── Admin/
    │   ├── SettingsPage.php         # Form Names habilitados
    │   └── ValidatorPage.php        # Tela de validação
    ├── Elementor/
    │   ├── SubmissionListener.php   # Gera o protocolo no envio
    │   └── SubmissionRepository.php # Leitura/gravação nas tabelas do Elementor
    └── Protocol/
        ├── Fingerprint.php          # Hash estável dos campos
        ├── Generator.php            # Montagem dos 18 dígitos
        └── Validator.php            # Conferência da assinatura
```

Os metadados do protocolo são gravados na tabela de valores das Submissions do Elementor (`e_submissions_values`), vinculados ao ID da submissão.

---

## Licença

GPL-2.0-or-later

---

## Autor

Luan Calasans
