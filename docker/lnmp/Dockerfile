FROM harbor.oneitfarm.com/deployv2/lnmp:php-7.1

#RUN curl -LO https://storage.googleapis.com/kubernetes-release/release/v1.8.1/bin/linux/amd64/kubectl && \
#    chmod +x ./kubectl && \
#    mv ./kubectl /usr/local/bin/kubectl && \
#    curl -LO https://storage.googleapis.com/kubernetes-helm/helm-v2.9.1-linux-amd64.tar.gz && \
#    tar xzvf helm-v2.9.1-linux-amd64.tar.gz && \
#    mv linux-amd64/helm /usr/local/bin/helm && \
#    rm -rf linux-amd64 helm-v2.9.1-linux-amd64.tar.gz

ENV QUEUE_WORKER_NUM 1
COPY queue_worker_init.sh /etc/my_init.d/

COPY schedule.cron /etc/cron.d/schedule
