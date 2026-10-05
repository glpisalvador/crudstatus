# Histórico de versões

O arquivo para download de cada versão está em [Releases](https://github.com/glpisalvador/crudstatus/releases).

## 3.0.0 — 2026-10-04

Reescrita completa para GLPI 11 e 12.

- Edita os status nativos de chamados, problemas e mudanças (nome, ícone, cor, ordem e disponibilidade) e cria status novos.
- Integração por **uma linha de ponte** no início dos métodos de status do núcleo, com backup, `php -l` e troca atômica.
- Tela de integração para aplicar e remover; reaplicação automática depois de atualizar o GLPI.
- A desinstalação remove a ponte e mantém as definições.
