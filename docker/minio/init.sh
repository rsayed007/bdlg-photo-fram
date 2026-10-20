#!/bin/sh
# Creates the bucket and makes only the public prefixes (final images and
# frame assets) anonymously readable. tmp/ and originals/ stay private.
set -e
mc --config-dir "${MC_CONFIG_DIR:-/tmp/.mc}" alias set local http://minio:9000 "$MINIO_ROOT_USER" "$MINIO_ROOT_PASSWORD"
mc --config-dir "${MC_CONFIG_DIR:-/tmp/.mc}" mb --ignore-existing "local/$AWS_BUCKET"
mc --config-dir "${MC_CONFIG_DIR:-/tmp/.mc}" anonymous set none "local/$AWS_BUCKET"
mc --config-dir "${MC_CONFIG_DIR:-/tmp/.mc}" anonymous set download "local/$AWS_BUCKET/public"
echo "bucket $AWS_BUCKET ready"
