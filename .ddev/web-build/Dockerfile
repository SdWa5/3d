# Blender is the geometry engine of this project: the PHP CLI shells out to it in
# background mode to build models from the specs. It is pinned so the .blend files
# produced in here open in the host GUI without a version mismatch — 4.3.2 is what
# Debian 13 (trixie) ships as `blender`, so host and container stay in lockstep.
# To bump: change both ARGs, rebuild with `ddev debug rebuild`, and install the same
# version on the host.
ARG BLENDER_SERIES=4.3
ARG BLENDER_VERSION=4.3.2

# Even in --background mode Blender links against the X/GL client libraries, so they have to be
# present; nothing here starts a display server. This is exactly the set `ldd` reports as missing
# on a bare Debian 13 — if a Blender bump adds one, `ldd /opt/blender/blender | grep 'not found'`
# names it.
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        xz-utils \
        libgl1 libice6 libsm6 libx11-6 libxext6 libxfixes3 libxi6 libxrender1 libxxf86vm1 \
        libxcb1 libxkbcommon0; \
    rm -rf /var/lib/apt/lists/*

RUN set -eux; \
    curl -fsSL -o /tmp/blender.tar.xz \
        "https://download.blender.org/release/Blender${BLENDER_SERIES}/blender-${BLENDER_VERSION}-linux-x64.tar.xz"; \
    mkdir -p /opt/blender; \
    tar -xJf /tmp/blender.tar.xz -C /opt/blender --strip-components=1; \
    rm /tmp/blender.tar.xz; \
    ln -sf /opt/blender/blender /usr/local/bin/blender; \
    blender --version
