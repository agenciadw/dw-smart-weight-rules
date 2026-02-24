# DW Smart Weight Rules v0.0.1

![Plugin Version](https://img.shields.io/badge/version-0.0.1-blue.svg)
![WooCommerce Compatible](https://img.shields.io/badge/WooCommerce-9.x-96588a.svg)
![WordPress Compatible](https://img.shields.io/badge/WordPress-6.x-21759b.svg)
![WordPress](https://img.shields.io/badge/WordPress-5.8+-green.svg)
![WooCommerce](https://img.shields.io/badge/WooCommerce-6.0+-orange.svg)
![PHP](https://img.shields.io/badge/PHP-7.4+-purple.svg)

Plugin para WooCommerce que bloqueia a finalizacao de compra quando o peso do carrinho ultrapassa o limite configurado, removendo frete, bloqueando checkout e oferecendo solicitacao de orcamento via WhatsApp com itens selecionados.

## 🚀 Funcionalidades Principais

### ⚖️ Bloqueio por Peso no Carrinho
- **Limite configuravel**: define o peso maximo diretamente no painel admin
- **Bloqueio hard**: impede finalizar compra acima do limite
- **Frete oculto**: remove metodos de envio quando o limite e excedido
- **Atualizacao em tempo real**: sincroniza estado de bloqueio por AJAX ao alterar quantidade

### 💬 Orcamento via WhatsApp
- **Botao de orcamento**: aparece abaixo do checkout quando excede o limite
- **Mensagem personalizada**: template com placeholders configuraveis
- **Itens selecionados**: envia apenas produtos marcados para checkout (compatibilidade com seletor)
- **Numero customizavel**: define numero WhatsApp no painel

### 🧩 Compatibilidade com seu Ecossistema
- **DW Select Cart Products**: considera `selected_for_checkout` no peso e na cotacao
- **DW Shareable Shopping Cart**: funciona com carrinho restaurado por URL
- **Woodmart Mini Cart**: bloqueia o botao de checkout do mini carrinho

### 🎛️ Painel Administrativo
- **WooCommerce > DW Peso Maximo**
- **Campos de configuracao**:
  - Peso maximo
  - Mensagem no carrinho/checkout
  - Numero WhatsApp
  - Mensagem WhatsApp
  - Texto do botao de orcamento
  - Template da mensagem de orcamento

## 📋 Requisitos

- **WordPress**: 5.8 ou superior
- **WooCommerce**: 6.0 ou superior
- **PHP**: 7.4 ou superior

## 🔧 Instalacao

1. **Upload do plugin**:
   ```
   wp-content/plugins/dw-smart-weight-rules/
   ```

2. **Ativacao**:
   - Acesse WordPress Admin -> Plugins
   - Ative "DW Smart Weight Rules"

3. **Configuracao**:
   - Va para WooCommerce -> DW Peso Maximo
   - Ajuste limite, mensagens e WhatsApp

## ⚙️ Configuracao

### Placeholders disponiveis
- **Mensagem no carrinho/checkout**:
  - `{current_weight}`, `{max_weight}`, `{unit}`, `{whatsapp_button}`
- **Mensagem para WhatsApp**:
  - `{current_weight}`, `{max_weight}`, `{unit}`, `{cart_items}`, `{subtotal}`
- **Template de orcamento**:
  - `{current_weight}`, `{max_weight}`, `{unit}`, `{cart_items}`, `{subtotal}`

### Comportamento de bloqueio
- Acima do limite:
  - checkout bloqueado
  - frete oculto
  - botao de orcamento habilitado
- Abaixo do limite:
  - checkout liberado
  - frete normal
  - aviso removido automaticamente

## 🔌 Compatibilidade

### Plugins
- ✅ **DW Select Cart Products**
- ✅ **DW Shareable Shopping Cart**
- ✅ **WooCommerce core**

### Temas
- ✅ **Woodmart** (incluindo mini cart)
- ✅ **Temas WooCommerce compativeis com hooks padrao**

## 📱 Recursos Tecnicos

### Performance
- Carregamento condicional de assets
- Validacoes focadas em carrinho/checkout
- Atualizacao via AJAX apenas quando necessario

### Seguranca
- Nonces em requisicoes sensiveis
- Sanitizacao de opcoes no admin
- Escape de saida no frontend e links

## 🛠️ Desenvolvimento

### Estrutura do Plugin
```
dw-smart-weight-rules/
├── assets/
│   └── css/
│       └── frontend.css
├── includes/
│   ├── class-dw-swr-plugin.php
│   ├── class-dw-swr-settings.php
│   └── class-dw-swr-frontend.php
├── languages/
│   └── index.php
├── dw-smart-weight-rules.php
└── readme.md
```

## 📊 Changelog

### v0.0.1 (Fevereiro 2026)
- ✅ Estrutura inicial do plugin em classes
- ✅ Painel admin de configuracao
- ✅ Bloqueio de checkout por limite de peso
- ✅ Remocao de frete acima do limite
- ✅ Integracao de orcamento via WhatsApp
- ✅ Compatibilidade com DW Select Cart Products e DW Shareable Shopping Cart
- ✅ Bloqueio de checkout no mini cart (Woodmart)
- ✅ Sincronizacao AJAX em alteracoes de quantidade
- ✅ Filtro por categorias: aplicar regra apenas a produtos das categorias selecionadas

## 🐛 Suporte e Bugs

### Reportar Problemas
- **GitHub**: https://github.com/agenciadw
- **Email**: david@dwdigital.com.br

## 📄 Licenca

Este plugin e licenciado sob GPL v2 ou posterior.

## 👨‍💻 Desenvolvedor

**David William da Costa**
- GitHub: [@agenciadw](https://github.com/agenciadw)
- Website: [DW Digital](https://dwdigital.com.br)
- Email: david@dwdigital.com.br

---

**Se este plugin foi util para voce, considere dar uma estrela no GitHub.**
