FROM node:26-alpine3.23

WORKDIR /app
COPY package*.json ./
RUN npm install --production
COPY . .

EXPOSE 3003
CMD ["node", "src/index.js"]
