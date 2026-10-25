FROM nginx:stable-alpine

# Build context is the repository root: the static tree (hashed Vite assets,
# brand images, well-known files) is served from here. The client build runs in
# devops/deploy/scripts/build-assets.sh before this image is built.
COPY public /var/www/html/public
COPY devops/docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY devops/docker/nginx/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh
ENTRYPOINT ["/entrypoint.sh"]
CMD ["nginx", "-g", "daemon off;"]
