# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2026-11-05

### Added

- `Client` with PSR-18 / PSR-17 auto-discovery and immutable `ClientOptions`.
- Sending SMS via `POST /v1/messages`: plain text, templated, scheduled, with `deleteId`.
- Deleting scheduled messages via `DELETE /v1/messages/delete` (`messages()->delete()`).
- Sender names via `GET /v1/messages/senders` (`messages()->senders()`).
- Account balance via `GET /account/balance` (`account()->balance()`).
- Service status via `GET /system/status` (`system()->status()`), maintenance returned as a status.
- Immutable `SmsMessage` value object with recipient normalization and validation.
- `Template` with strict `{placeholder}` rendering and `TemplateRegistry`.
- Typed `SendSmsResponse` with parsed accepted / rejected recipient lists.
- Exception hierarchy mapped from HTTP status codes, all implementing `TaqnyatException`.
