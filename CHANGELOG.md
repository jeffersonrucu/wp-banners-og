# Changelog

Formato baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/);
versionamento [SemVer](https://semver.org/lang/pt-BR/).

## [2.2.0]

### Added

- **Banner por termo**: categorias e tags ganham o próprio banner, editado na tela
  de edição do termo e regerado ao atualizar — antes todos os arquivos dividiam o
  banner padrão do layout. Vale para `category` e `post_tag`, mais `product_cat` e
  `product_tag` quando o WooCommerce está ativo.
- Os placeholders saem do próprio termo: nome, descrição e, em categoria de
  produto, a imagem da categoria. O layout `product` numa categoria já vem sem
  preço, que ali não teria de qual produto falar.
- A descrição do termo passa a alimentar a `og:description` do arquivo, no lugar
  da tagline do site.
- Filtros `banners_og_taxonomies`, `banners_og_default_kind_for_taxonomy` e
  `banners_og_term_defaults`.
- O Diagnóstico lista também os banners de termos.

### Notes

- A criação de categoria pela coluna da esquerda é AJAX e não comporta o gerador:
  o banner nasce no primeiro **Atualizar** da categoria já criada.

## [2.1.0]

### Added

- Integração com **WooCommerce** (`Banners_OG_Woocommerce`), carregada só com o
  plugin ativo: post type `product` no metabox, layout `product` com foto e preço,
  campo **Preço** e placeholders vindos do próprio produto (categoria, descrição
  curta e `get_price_html()`).
- **Ponte para os plugins de SEO** (`Banners_OG_Seo`): com Yoast, Rank Math, AIOSEO
  ou SEOPress ativo o plugin já não imprimia as próprias tags, e agora entrega o
  banner para quem imprime, pelos filtros públicos de cada um. Desligável pelo
  filtro `banners_og_seo_bridge`.
- Filtro `banners_og_post_defaults`, para derivar os placeholders do metabox do
  conteúdo que está sendo editado.
- Filtro `banners_og_product_image_url`, para trocar a foto usada pelo layout
  `product` — útil quando as imagens são servidas por CDN.
- Campos com **escopo de layout** (`kinds`): um campo só aparece nos layouts que o
  imprimem, e o formulário do metabox troca junto com o select de layout.
- Tipos de campo **`image`** (media picker) e **`toggle`** (checkbox), com
  `placeholder` próprio, além de `text` e `textarea`.
- Campo **Marca**: a assinatura do banner passa a ser editável por banner, com o
  nome da tela de Aparência como fallback.
- No layout `product`, os campos **Foto**, **Mostrar a foto** e **Mostrar o
  preço**. O preço não é digitável: vem sempre do produto.
- O layout `product` assina com a marca (logo horizontal, ou o símbolo) no topo
  do conteúdo: antes ela só aparecia quando não havia foto.
- Filtro `banners_og_fields_for_context`, que deixa um campo se apresentar de
  formas diferentes na tela geral e no metabox — é como o campo **Foto** fica
  restrito ao editor do produto, já que na tela geral não há produto algum e o
  símbolo da marca já responde pelo fallback.
- Filtro `banners_og_uploads_url`, para servir os banners de outro lugar.
- Tela **Diagnóstico**: onde os banners são gravados, a URL publicada, o status
  HTTP dessa URL e os sinais de offload de uploads, com relatório em texto puro.

### Changed

- A escolha da imagem do request virou `Banners_OG_Meta::current_image()`, pública,
  para que as meta tags e a ponte de SEO publiquem sempre o mesmo arquivo.
- A sanitização dos campos ficou toda em `Banners_OG_Templates::sanitize_fields()`,
  por tipo de campo, em vez de repetida no metabox e no AJAX.

### Fixed

- Banners deixavam de abrir em site com offload de uploads (S3 e afins): a URL
  apontava para o bucket, que nunca recebe esses arquivos — eles não são
  attachments — e respondia `AccessDenied`. Nesses sites o banner passa a ser
  gravado como attachment, para o plugin de offload subir e servir; nos demais
  continua sendo arquivo solto, fora da biblioteca. Filtros
  `banners_og_use_attachments` e `banners_og_uploads_url`.
- Imagens de outro domínio (CDN, offload) apareciam no preview e sumiam do
  arquivo gerado — o html2canvas não desenha imagem cross-origin. Vale para a
  foto do produto e para o logo e o símbolo da marca: agora responde a cópia
  local em `uploads/` e, quando ela não existe, o próprio site serve a imagem
  por `admin-ajax.php` (só ID de anexo, com `edit_posts` e nonce). O Diagnóstico
  mostra qual caminho está em uso.
- Campos `toggle` nasciam desligados no metabox, ignorando o padrão do layout:
  marcar "Customize" apagava a foto e o preço do preview. Sem valor gravado para
  aquele conteúdo, quem responde é a tela Banners OG.
- O símbolo da marca continuava sumindo do arquivo quando vinha do **ícone do
  site**: só o campo da tela Aparência passava pelo resolvedor de imagem. O
  ícone do site é anexo como qualquer outro e agora segue o mesmo caminho.
- Título, subtítulo e rodapé longos empurravam o resto do banner para fora do
  canvas: passam por `clamp()` e o título desce um degrau de corpo conforme o
  tamanho. No layout `product` a assinatura não quebra mais em duas linhas.
- Assets versionados pelo arquivo, e não só pela versão do plugin: dentro de uma
  mesma versão o navegador servia o JS antigo.

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
