# Política de segurança

## Versões suportadas

Correções de segurança saem para a última versão publicada.

| Versão | Suportada |
| --- | --- |
| 2.x | sim |
| 1.x | não |

## Reportando

**Não abra issue pública para vulnerabilidade.**

Use o [Security Advisories](https://github.com/jeffersonrucu/wp-banners-og/security/advisories/new)
do repositório, que é privado até a correção sair.

Ajuda muito no relato:

- a versão do plugin, do WordPress e do PHP;
- o papel do usuário necessário para explorar (assinante, autor, administrador);
- os passos mínimos para reproduzir.

Respondo em até 7 dias.

## Superfície de ataque

Os pontos que mais valem atenção em uma auditoria:

- **`wp_ajax_banners_og_save_default`** — exige `manage_options` e nonce; grava a
  option dos banners padrão e um arquivo em `uploads/banners-og/`.
- **`wp_ajax_banners_og_save_post`** — exige `edit_post` no post alvo e nonce;
  grava post meta e um arquivo em `uploads/banners-og/`.
- **Upload** — o arquivo chega como upload multipart e passa por
  `wp_handle_upload()` restrito a `image/jpeg`, com verificação de
  `getimagesize()`, do tipo JPEG e das dimensões exatas de 1200 × 630, além de um
  teto de 4 MB. Nada é adicionado à biblioteca de mídia.
- **Saída no front-end** — `Banners_OG_Meta` imprime apenas meta tags, com
  `esc_attr()` e `wp_strip_all_tags()`.
- **Configuração** — a tela de Aparência usa a Settings API com
  `sanitize_callback`; cores passam por `sanitize_hex_color()`, font stacks têm os
  caracteres que poderiam escapar da declaração CSS removidos, e a URL de webfont
  passa por `esc_url_raw()` limitada a `http`/`https`.
