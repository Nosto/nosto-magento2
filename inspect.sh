#!/bin/bash

docker run --env GITHUB_WORKSPACE=/app --volume="$(pwd)"/.:/app docker.pkg.github.com/mridang/action-phpstorm/stormy:latest /app /app/.idea/inspectionProfiles/CI.xml /tmp v2 Inspection
