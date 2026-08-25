# Contribuindo

## Ambiente

```bash
composer install
```

Não há build de assets: o CSS e o JS do plugin são servidos como estão.

## Antes de abrir um PR

```bash
composer phpcs          # WordPress Coding Standards + PHPCompatibility (PHP 7.4+)
composer phpcbf         # corrige o que é automático
composer phpstan        # análise estática, nível 6
composer check-version  # header do plugin × config.php × readme.txt
```

Os três primeiros rodam no CI, junto com `php -l` em PHP 7.4 até 8.4.

## Plugin Check

O pacote que vai para o WordPress.org é montado por `bin/build-dist.sh`, que usa
o `.distignore` para deixar fora tudo que é ferramenta de desenvolvimento. É
nesse pacote que o [Plugin Check](https://wordpress.org/plugins/plugin-check/)
deve rodar:

```bash
composer dist
wp plugin check dist/banners-og
```

Rodar o checker direto no clone acusa `bin/` e `tests/` por não terem guarda de
acesso direto — são arquivos de CLI, que não fazem parte do pacote.

> `.distignore` e o `export-ignore` do `.gitattributes` listam a mesma coisa por
> motivos diferentes: o primeiro alimenta o `bin/build-dist.sh`, o segundo limpa
> os arquivos que o GitHub gera sozinho. Ao excluir algo novo, atualize os dois.

## Estilo

- PHP com tabs, arrays curtos, sem Yoda conditions — o `phpcs.xml.dist` é a
  referência, e o `phpcbf` resolve a maior parte.
- Prefixos: `Banners_OG_` em classes, `BANNERS_OG_` em constantes, `banners_og_`
  em hooks, options e meta. Text domain `banners-og`.
- Comentários em inglês, explicando **por que**, não o quê.
- Conversa e documentação em português; código e commits em inglês.

## Layouts

Antes de propor um layout novo dentro do plugin, veja se ele não cabe nos pontos
de extensão (`banners_og_kinds`, `banners_og_fields`,
`banners_og_template_defaults`, `banners_og_enqueue_assets` e
`window.BannersOG.registerTemplate()`), documentados no README. Layout de marca
específica vive melhor no tema ou num plugin próprio.

Se for um layout genérico, ele precisa:

- medir exatamente 1200 × 630 no nó raiz — a validação no PHP rejeita outro tamanho;
- ler cor e fonte das custom properties (`--bog-accent`, `--bog-font-heading`, …),
  nunca valores fixos;
- funcionar sem logo e sem símbolo configurados;
- não usar a marca do site como arte de fundo;
- evitar `filter`, `mask` e sombras exóticas, que o html2canvas não rasteriza fielmente.

## Commits

[Conventional Commits](https://www.conventionalcommits.org/), em inglês e no
imperativo: `feat:`, `fix:`, `refactor:`, `chore:`, `docs:`, `test:`, `perf:`,
`ci:`. Um assunto por commit.

## Release

1. Suba a versão em `banners-og.php`, `config.php` e `readme.txt` (`Stable tag`).
2. Adicione a entrada no `CHANGELOG.md` e no changelog do `readme.txt`.
3. Confirme com `composer check-version`.
4. Marque a tag `vX.Y.Z` e faça o push — o workflow de release valida tudo de
   novo, roda o Plugin Check e publica o zip.
