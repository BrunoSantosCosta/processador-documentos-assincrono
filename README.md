# Processador de Documentos Assíncrono

Projeto de aprendizado: uma aplicação que recebe documentos, extrai metadados (nome, tamanho, tipo, hash SHA-256 e páginas se for PDF) e acompanha o status do processamento.

A documentação cresce à medida que cada etapa for validada. Este README começa mínimo de propósito.

## Em construção

A arquitetura final prevista é:

```text
React → Laravel API → S3 → SQS → Go Worker → Banco
```

Começamos local e simples. Cada tecnologia entra só quando resolver um problema concreto.

## Tecnologias planejadas

- React
- PHP / Laravel
- Go
- SQLite (início)
- Amazon S3
- Amazon SQS
- Docker
- AWS / CloudFormation
- GitHub Actions

## Estrutura

```text
backend/    Laravel API (Etapa 2)
frontend/   React (Etapa 3)
worker/     Go (Etapa 7)
infra/      CloudFormation (Etapa 11)
```

## Segurança

Este repositório será público. Credenciais reais nunca entram no Git.

- Copie `.env.example` para `.env` e preencha localmente.
- `.env` está no `.gitignore`.
- Não coloque AWS keys, senhas, tokens ou dados pessoais em nenhum arquivo versionado.
