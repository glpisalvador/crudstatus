# CRUD Status para GLPI

> Autor: **GLPI Salvador** · Licença: **GPLv2+** · Compatível com GLPI **11.0.0 a 12.x**

Permite **editar os status nativos** e **criar status novos** para chamados, problemas e mudanças, direto pela tela do GLPI.

## O que o plugin faz

### Status
- **Lista** dos status de cada tipo de item: os nativos, lidos do próprio GLPI, e os criados pelo plugin.
- **Editar** nome, **ícone** (seletor com busca entre os ícones Tabler do GLPI), **cor** e **ordem** em que aparecem.
- **Disponibilidade:** esconder um status das listas sem apagá-lo.
- **Criar status novos**, com número próprio a partir de um número inicial configurável.
- **Excluir** um status criado, escolhendo para qual status os itens que estavam nele serão movidos.
- **Status padrão** de cada tipo.
- **Voltar ao padrão** do GLPI quando quiser.
- As cores e os ícones aparecem nas listas e nos formulários do GLPI.

### Como funciona por dentro
O GLPI não tem um ponto de extensão para status. Por isso o plugin insere **uma única linha marcada** (`// crudstatus:ponte`) no início dos métodos de status do núcleo, sem tocar no resto dos arquivos:
- antes de gravar, faz **backup** dos arquivos originais por versão do GLPI, confere com `php -l` e troca o arquivo de forma atômica;
- preserva alterações feitas por outros plugins no núcleo;
- a tela **Integração** mostra a situação da ponte em cada arquivo e método, com botões para **aplicar** e **remover**;
- depois de **atualizar o GLPI**, a ação automática **reaplica** a ponte sozinha, se essa opção estiver ligada;
- a **desinstalação remove a ponte** e deixa o núcleo como era. As definições continuam guardadas e voltam ao reinstalar.

## Configuração

- Reaplicar a ponte automaticamente após atualizar o GLPI.
- Número inicial dos status novos.

## Requisitos extras

O usuário do servidor web precisa poder gravar nos arquivos do núcleo que recebem a ponte (`src/CommonITILObject.php`, `src/Ticket.php`, `src/Problem.php`, `src/Change.php`).

---

## Download e instalação

1. Baixe o arquivo `crudstatus-X.Y.Z.zip` da **[última versão](../../releases/latest)**. Use o arquivo anexado à release, não o "Source code".
2. Descompacte dentro da pasta `plugins/` do GLPI. O resultado deve ser `plugins/crudstatus/setup.php`.
3. Ajuste o dono dos arquivos para o usuário do servidor web, por exemplo:
   ```bash
   chown -R www-data:www-data /var/www/glpi/plugins/crudstatus
   ```
4. No GLPI, vá em **Configurar → Plugins** e clique em **Instalar** e depois em **Ativar**. Pela linha de comando:
   ```bash
   php bin/console plugin:install crudstatus -u <usuário administrador>
   php bin/console plugin:activate crudstatus
   ```

A instalação cria as tabelas, as configurações padrão e as ações automáticas do plugin, e funciona num GLPI sem nada configurado antes.

### Atualização

Substitua a pasta `plugins/crudstatus` pela versão nova e rode **Instalar** de novo, ou `php bin/console plugin:install crudstatus -f`. Depois, ative o plugin. As tabelas e colunas novas são criadas sem perder os dados.

### Desinstalação

A desinstalação **não apaga as tabelas do plugin**: reinstalar recupera os dados.

## Versões

O histórico, com o que mudou em cada versão e o arquivo para download, está em **[Releases](../../releases)**. Cada versão entrou por um **[pull request](../../pulls?q=is%3Apr)**.

## Licença

Distribuído sob a **GNU General Public License v2.0 ou posterior**. Veja o arquivo [LICENSE](LICENSE).