#!/bin/bash

# Build script for Railway deployment
echo "Installing dependencies..."
npm install

echo "Building assets..."
npm run build

echo "Setting up demo data..."
php setup_demo.php

echo "Setting permissions..."
chmod 755 data/
chmod 644 data/*.json

echo "Build complete!"
