#!/bin/bash
# Start the backend API server on port 8000
# Usage: bash start-backend.sh

BACKEND_DIR="$(cd "$(dirname "$0")" && pwd)/backend/public"

# Check if port 8000 is already in use
if lsof -i :8000 > /dev/null 2>&1; then
    echo "Port 8000 is already in use. Killing existing process..."
    kill $(lsof -ti :8000) 2>/dev/null
    sleep 1
fi

echo "Starting backend server on http://localhost:8000"
echo "Document root: $BACKEND_DIR"
echo ""
echo "Press Ctrl+C to stop."
echo ""

php -S localhost:8000 -t "$BACKEND_DIR"
