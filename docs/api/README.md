# API Documentation

This directory contains the baseline OpenAPI contract for the Fleet Management System.

## Available Artifact

- `openapi.yaml`: current machine-readable API contract for authentication and reporting/export workflows

## Usage

1. Open `openapi.yaml` in Swagger Editor, Stoplight, Insomnia, or Postman.
2. Use it as the source of truth for frontend integration and external client onboarding.
3. Extend the schemas and paths incrementally as new modules evolve instead of creating disconnected ad hoc docs.

## Current Scope

- SPA authentication endpoints
- current-user profile endpoint
- Phase 5 analytics dashboard
- report dataset endpoints
- report export queue, detail, and download endpoints

## Deferred Expansion

- complete CRUD coverage for every module
- webhook definitions for future integrations
- generated reference docs from annotations or route introspection
