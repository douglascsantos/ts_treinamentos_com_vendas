<?php
/**
 * Arquivo de versão do site — TS Treinamentos em Saúde
 *
 * Esquema de versionamento: PROTOTIPO.MACRO.MICRO
 *  - PROTOTIPO: 0 enquanto o site estiver em fase de protótipo/validação.
 *               Passa a 1 quando o site entra oficialmente em produção definitiva.
 *  - MACRO:     incrementa em mudanças grandes que afetam o site inteiro
 *               (ex.: nova seção estrutural, reorganização de dobras, nova stack).
 *  - MICRO:     incrementa em ajustes pontuais/localizados (ex.: texto, cor de um
 *               botão, correção de bug, ajuste de imagem).
 *
 * Cada nova versão ganha um codinome. Atualize este arquivo a cada alteração
 * solicitada — é a fonte única de verdade exibida no rodapé do site.
 * Ver CHANGELOG.md na raiz do projeto para o histórico de cada versão.
 */

return [
    'brand'      => 'TS Treinamentos',
    'year'       => date('Y'),
    'version'    => '2.2.1',
    'codename'   => 'Clara',
    'stage'      => 'production',
    'updated_at' => '2026-07-31',
];
