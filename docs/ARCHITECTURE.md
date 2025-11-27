# Architecture Documentation

## System Overview
See main README for architecture diagram.

## Service Communication
- Synchronous: HTTP/REST
- Asynchronous: RabbitMQ

## Database Strategy
Each service has its own database (Database per Service pattern).
