# Multi-stage Alpine image with pgvector on PostgreSQL 18
FROM postgres:18-alpine AS builder

RUN apk add --no-cache \
    git \
    build-base \
    clang \
    llvm-dev

WORKDIR /tmp
RUN git clone --depth 1 https://github.com/pgvector/pgvector.git \
    && cd pgvector \
    && make with_llvm=no \
    && make install with_llvm=no

FROM postgres:18-alpine

COPY --from=builder /usr/local/lib/postgresql/vector.so /usr/local/lib/postgresql/
COPY --from=builder /usr/local/share/postgresql/extension/vector* /usr/local/share/postgresql/extension/
