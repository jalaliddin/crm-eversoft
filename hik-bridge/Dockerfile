FROM node:22-alpine
WORKDIR /app
COPY package.json ./
COPY src ./src
USER node
VOLUME ["/app/state"]
ENV STATE_FILE=/app/state/bridge-state.json
CMD ["node", "src/index.js"]
