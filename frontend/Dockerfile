# Interface React AndTabbax + serveur HTTP de la pile.
#
# Ce conteneur est la seule porte d'entrée : il sert les fichiers statiques
# construits par Vite et relaie /api en FastCGI vers le conteneur `backend`.
# Le frontend et l'API partagent ainsi la même origine — pas de CORS, et le
# jeton JWT voyage sur des chemins relatifs (`baseURL: '/api'`, src/api/client.js).

# --- Étape 1 : construction du bundle ----------------------------------------
FROM node:24-alpine AS build

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY . .
RUN npm run build

# --- Étape 2 : service HTTP --------------------------------------------------
FROM nginx:1.29-alpine

COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/dist /usr/share/nginx/html

EXPOSE 80
