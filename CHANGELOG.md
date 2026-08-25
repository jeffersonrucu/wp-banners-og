# Changelog

Formato baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/);
versionamento [SemVer](https://semver.org/lang/pt-BR/).

## [2.0.0]

### Added

- Tela **Aparência**: paleta (7 cores), par tipográfico, nome da marca, logo
  horizontal e símbolo quadrado, com fallback no logo do tema e no site icon.
- Tipografia por **presets** (`banners_og_font_presets`), com amostra ao vivo; as
  font stacks manuais e a URL de webfont ficam num bloco avançado, só para quem
  escolhe "Custom".
- Registro extensível de layouts: filtros `banners_og_kinds`, `banners_og_fields` e
  `banners_og_template_defaults` no PHP, `window.BannersOG.registerTemplate()` no JS
  e a action `banners_og_enqueue_assets` para CSS/JS próprios.
- `uninstall.php` removendo options, post meta e os arquivos gerados.
- `.distignore` e `bin/build-dist.sh` para montar o pacote de distribuição, mais
  `readme.txt` no padrão do WordPress.org.

### Changed

- Plugin renomeado para **Banners OG**: prefixos `BANNERS_OG_*` / `Banners_OG_*` /
  `banners_og_*`, text domain `banners-og`, pasta `uploads/banners-og/`. O nome
  gera o slug `banners-og` no WordPress.org, casando com o text domain.
- Layouts genéricos (`cover`, `feature`, `article`, `profile`) com cores e fontes
  lidas de CSS custom properties, no lugar dos 4 layouts de marca fixa.
- Fundo chapado com detalhe geométrico próprio (`.bog-corner`) em vez do símbolo
  em tile: marca repetida só funciona com arte desenhada para isso.
- A pasta `uploads/banners-og/` passa a ser criada na primeira gravação, e não na
  ativação — ativar com WP-CLI como root deixava a pasta sem permissão de escrita
  para o servidor web.
- Falha de upload devolve o motivo real do `wp_handle_upload()`, em vez de uma
  mensagem genérica.
- Post types padrão passam a ser `post` e `page`.
- O banner é enviado como upload multipart e gravado por `wp_handle_upload()`,
  no lugar do data URL em base64 com `file_put_contents()`.
- Arquivo antigo removido com `wp_delete_file()`.
- Requisito mínimo baixado para WordPress 5.9 e PHP 7.4.

### Removed

- Textos, paleta, fontes e imagens da marca original; nenhuma requisição externa
  de fonte por padrão.

### Migration

As chaves de option, de post meta e a pasta de uploads mudaram, e os nomes dos
layouts também. Banners e configurações da 1.x **não** são migrados: regere depois
de atualizar.

## [1.1.0]

### Changed

- Banners passam a ser gravados como arquivos em `uploads/` em vez de attachments —
  não aparecem mais na biblioteca de mídia e não podem ser apagados por engano.
  Cada regeração substitui o arquivo anterior.

## [1.0.0]

### Added

- Página admin com os 4 templates padrão.
- Metabox por conteúdo, com geração automática do banner ao salvar o post.
- Saída de meta tags `og:*` e `twitter:*` no `wp_head`, com detecção de plugins de SEO.
- Geração no navegador via html2canvas, upload por AJAX e validação do JPEG no PHP.
