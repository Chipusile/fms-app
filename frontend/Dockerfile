FROM node:22-alpine

WORKDIR /app

COPY docker/entrypoint.sh /usr/local/bin/fms-frontend-entrypoint

RUN chmod +x /usr/local/bin/fms-frontend-entrypoint

ENTRYPOINT ["fms-frontend-entrypoint"]
CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0", "--port", "5173"]
