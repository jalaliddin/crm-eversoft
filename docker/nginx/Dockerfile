# Production web image: builds the React SPA and serves it with the reverse-proxy config.
FROM node:22-alpine AS frontend
WORKDIR /app
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci
COPY frontend/ ./
ARG VITE_API_URL=/api/v1
ENV VITE_API_URL=$VITE_API_URL
RUN npm run build

# Eversoft Terminal Bridge browser extension, offered for download from the devices page.
FROM alpine:3.20 AS extension
RUN apk add --no-cache zip
COPY browser-bridge/ /ext/
RUN cd /ext && zip -qrX /eversoft-terminal-bridge.zip manifest.json background.js content.js options.html options.js lib icons

FROM nginx:1.27-alpine AS prod
COPY nginx/snippets /etc/nginx/snippets
COPY nginx/production.conf /etc/nginx/conf.d/default.conf
COPY --from=frontend /app/dist /usr/share/nginx/html
COPY --from=extension /eversoft-terminal-bridge.zip /usr/share/nginx/html/downloads/eversoft-terminal-bridge.zip
COPY backend/public /var/www/backend/public
EXPOSE 80 443

FROM nginx:1.27-alpine AS dev
COPY nginx/snippets /etc/nginx/snippets
COPY nginx/development.conf /etc/nginx/conf.d/default.conf
EXPOSE 80
