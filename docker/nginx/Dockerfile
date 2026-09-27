# Production web image: builds the React SPA and serves it with the reverse-proxy config.
FROM node:22-alpine AS frontend
WORKDIR /app
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci
COPY frontend/ ./
ARG VITE_API_URL=/api/v1
ENV VITE_API_URL=$VITE_API_URL
RUN npm run build

FROM nginx:1.27-alpine AS prod
COPY nginx/snippets /etc/nginx/snippets
COPY nginx/production.conf /etc/nginx/conf.d/default.conf
COPY --from=frontend /app/dist /usr/share/nginx/html
COPY backend/public /var/www/backend/public
EXPOSE 80 443

FROM nginx:1.27-alpine AS dev
COPY nginx/snippets /etc/nginx/snippets
COPY nginx/development.conf /etc/nginx/conf.d/default.conf
EXPOSE 80
