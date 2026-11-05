FROM nginx:1.27-alpine

COPY gateway/proxy_params.conf /etc/nginx/proxy_params.conf
COPY gateway/nginx.conf /etc/nginx/conf.d/default.conf

COPY certs/sigeru.crt /etc/nginx/certs/sigeru.crt
COPY certs/sigeru.key /etc/nginx/certs/sigeru.key