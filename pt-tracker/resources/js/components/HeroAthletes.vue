<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(defineProps<{ progress?: number }>(), {
    progress: 0,
});

type Point = { x: number; y: number };
const motion = computed(() => {
    const value = Math.min(1, Math.max(0, props.progress));
    return value * value * (3 - 2 * value);
});
const point = (x: number, y: number): Point => ({ x, y });
const number = (value: number) => Number(value.toFixed(3));
const coordinates = (value: Point) => `${number(value.x)} ${number(value.y)}`;

function muscle(from: Point, to: Point, top: number, bottom: number): string {
    const length = Math.hypot(to.x - from.x, to.y - from.y) || 1;
    const dx = (to.x - from.x) / length;
    const dy = (to.y - from.y) / length;
    const offset = (origin: Point, along: number, across: number) =>
        coordinates(
            point(
                origin.x + dx * along - dy * across,
                origin.y + dy * along + dx * across,
            ),
        );

    return `M${offset(from, 0, top)} C${offset(from, length * 0.3, top * 1.16)} ${offset(to, -length * 0.25, bottom * 1.15)} ${offset(to, 0, bottom)} Q${offset(to, bottom, 0)} ${offset(to, 0, -bottom)} C${offset(to, -length * 0.25, -bottom * 1.15)} ${offset(from, length * 0.3, -top * 1.16)} ${offset(from, 0, -top)} Q${offset(from, -top * 0.75, 0)} ${offset(from, 0, top)}Z`;
}

const pose = computed(() => {
    const t = motion.value;
    return {
        leftShoulder: point(128 - t * 4, 188 - t * 4),
        rightShoulder: point(272 + t * 4, 188 - t * 4),
        leftElbow: point(112 - t * 21, 259 - t * 12),
        rightElbow: point(288 + t * 21, 259 - t * 12),
        leftHand: point(153 + t * 8, 307 - t * 19),
        rightHand: point(247 - t * 8, 307 - t * 19),
        torso: `translate(200 250) scale(${number(0.91 + t * 0.09)} ${number(1 - t * 0.015)}) translate(-200 -250)`,
        head: `translate(0 ${number(-t * 4)})`,
    };
});

const squat = computed(() => {
    const t = motion.value;
    const shoulder = point(200, 274 - t * 108);
    const hip = point(200, 415 - t * 108);
    return {
        shoulder,
        hip,
        leftHip: point(hip.x - 24, hip.y),
        rightHip: point(hip.x + 24, hip.y),
        leftKnee: point(150, 448 - t * 34),
        rightKnee: point(250, 448 - t * 34),
        leftAnkle: point(150, 526),
        rightAnkle: point(250, 526),
        leftShoulder: point(shoulder.x - 43, shoulder.y + 15),
        rightShoulder: point(shoulder.x + 43, shoulder.y + 15),
        leftElbow: point(shoulder.x - 62, shoulder.y + 70),
        rightElbow: point(shoulder.x + 62, shoulder.y + 70),
        leftHand: point(112, shoulder.y + 10),
        rightHand: point(288, shoulder.y + 10),
        torso: `M${shoulder.x - 40} ${shoulder.y + 3} Q${shoulder.x - 60} ${shoulder.y + 24} ${shoulder.x - 46} ${shoulder.y + 65} L${hip.x - 31} ${hip.y} Q${hip.x} ${hip.y + 9} ${hip.x + 32} ${hip.y} L${shoulder.x + 47} ${shoulder.y + 65} Q${shoulder.x + 60} ${shoulder.y + 24} ${shoulder.x + 40} ${shoulder.y + 3} Q${shoulder.x} ${shoulder.y - 12} ${shoulder.x - 40} ${shoulder.y + 3}Z`,
        shorts: `M${hip.x - 33} ${hip.y - 20} Q${hip.x} ${hip.y - 12} ${hip.x + 33} ${hip.y - 20} L${hip.x + 44} ${hip.y + 35} L${hip.x + 8} ${hip.y + 46} L${hip.x} ${hip.y + 18} L${hip.x - 8} ${hip.y + 46} L${hip.x - 44} ${hip.y + 35}Z`,
        head: `translate(${shoulder.x} ${shoulder.y - 43})`,
        bar: `translate(0 ${number(shoulder.y + 10)})`,
    };
});
</script>

<template>
    <div
        class="hero-athletes"
        role="img"
        aria-label="A bodybuilder opening into a front lat spread beside a powerlifter standing up from a squat in a power rack."
    >
        <figure class="athlete-scene posing-scene" aria-hidden="true">
            <svg viewBox="0 0 400 600" fill="none" focusable="false">
                <defs>
                    <linearGradient
                        id="hero-pose-body"
                        x1="95"
                        y1="180"
                        x2="290"
                        y2="260"
                        gradientUnits="userSpaceOnUse"
                    >
                        <stop stop-color="#0e171a" />
                        <stop offset=".34" stop-color="#3d4849" />
                        <stop offset=".55" stop-color="#253134" />
                        <stop offset="1" stop-color="#0b1215" />
                    </linearGradient>
                    <linearGradient
                        id="hero-pose-head"
                        x1="173"
                        y1="100"
                        x2="223"
                        y2="140"
                        gradientUnits="userSpaceOnUse"
                    >
                        <stop stop-color="#56605b" />
                        <stop offset=".45" stop-color="#303b3b" />
                        <stop offset="1" stop-color="#111a1d" />
                    </linearGradient>
                    <radialGradient id="hero-pose-gold">
                        <stop stop-color="#f0ca7c" stop-opacity=".38" />
                        <stop
                            offset=".5"
                            stop-color="#d4a452"
                            stop-opacity=".16"
                        />
                        <stop
                            offset="1"
                            stop-color="#c9a669"
                            stop-opacity="0"
                        />
                    </radialGradient>
                    <linearGradient
                        id="hero-pose-rim"
                        x1="110"
                        y1="80"
                        x2="290"
                        y2="500"
                        gradientUnits="userSpaceOnUse"
                    >
                        <stop stop-color="#f2d69c" stop-opacity=".6" />
                        <stop
                            offset=".5"
                            stop-color="#cab17c"
                            stop-opacity=".22"
                        />
                        <stop
                            offset="1"
                            stop-color="#7b8987"
                            stop-opacity=".06"
                        />
                    </linearGradient>
                </defs>
                <ellipse
                    cx="200"
                    cy="265"
                    rx="190"
                    ry="255"
                    fill="url(#hero-pose-gold)"
                    class="pose-golden-light"
                />
                <ellipse
                    cx="200"
                    cy="548"
                    rx="113"
                    ry="15"
                    fill="#c8b58b"
                    opacity=".04"
                />
                <g
                    fill="url(#hero-pose-body)"
                    stroke="url(#hero-pose-rim)"
                    stroke-width="1.5"
                >
                    <path
                        d="M175 327Q149 340 146 377Q143 405 156 432L162 442Q169 452 180 435L194 391L199 344Z"
                    />
                    <path
                        d="M225 327Q251 340 254 377Q257 405 244 432L238 442Q231 452 220 435L206 391L201 344Z"
                    />
                    <path
                        d="M161 434Q149 459 157 482L162 516L157 530Q173 540 182 530L178 511L184 472Q188 448 177 438Z"
                    />
                    <path
                        d="M239 434Q251 459 243 482L238 516L243 530Q227 540 218 530L222 511L216 472Q212 448 223 438Z"
                    />
                    <path
                        :d="muscle(pose.leftShoulder, pose.leftElbow, 24, 19)"
                        class="pose-upper-arm"
                    />
                    <path
                        :d="muscle(pose.rightShoulder, pose.rightElbow, 24, 19)"
                        class="pose-upper-arm"
                    />
                    <path :d="muscle(pose.leftElbow, pose.leftHand, 18, 10)" />
                    <path
                        :d="muscle(pose.rightElbow, pose.rightHand, 18, 10)"
                    />
                    <g :transform="pose.torso" class="pose-torso">
                        <path
                            d="M174 155L155 166Q125 167 116 188Q107 206 126 235L148 274L169 318Q200 337 231 318L252 274L274 235Q293 206 284 188Q275 167 245 166L226 155Z"
                        />
                        <path
                            d="M197 178Q171 166 148 180Q132 192 142 210Q167 225 196 209Z"
                            fill="#344143"
                            stroke-opacity=".5"
                        />
                        <path
                            d="M203 178Q229 166 252 180Q268 192 258 210Q233 225 204 209Z"
                            fill="#293638"
                            stroke-opacity=".5"
                        />
                        <path
                            d="M137 218Q149 238 165 261L170 293Q151 265 137 245Z"
                            fill="#1c292c"
                            stroke-opacity=".4"
                        />
                        <path
                            d="M263 218Q251 238 235 261L230 293Q249 265 263 245Z"
                            fill="#172326"
                            stroke-opacity=".4"
                        />
                        <path
                            d="M182 222Q190 218 197 223L197 243Q185 245 180 239ZM203 223Q210 218 218 222L220 239Q215 245 203 243Z"
                            fill="#384345"
                            stroke-opacity=".3"
                        />
                        <path
                            d="M181 250Q190 247 197 251L197 268L183 270ZM203 251Q210 247 219 250L217 270L203 268Z"
                            fill="#2c393b"
                            stroke-opacity=".3"
                        />
                        <path
                            d="M184 278L197 276V294L189 307ZM203 276L216 278L211 307L203 294Z"
                            fill="#253235"
                            stroke-opacity=".3"
                        />
                    </g>
                    <path d="M182 146L179 165Q200 178 221 165L218 146Z" />
                </g>
                <g :transform="pose.head" class="pose-head">
                    <ellipse cx="175" cy="128" rx="5" ry="9" fill="#233032" />
                    <ellipse cx="225" cy="128" rx="5" ry="9" fill="#172326" />
                    <path
                        d="M176 119Q173 91 200 89Q227 91 224 119L220 146Q211 162 200 163Q189 162 180 146Z"
                        fill="url(#hero-pose-head)"
                        stroke="url(#hero-pose-rim)"
                        stroke-width="1.5"
                    />
                </g>
                <path
                    d="M166 311Q200 325 234 311L226 339L201 358L176 339Z"
                    fill="#090f12"
                    stroke="#485553"
                    stroke-opacity=".3"
                />
                <g
                    fill="url(#hero-pose-body)"
                    stroke="url(#hero-pose-rim)"
                    stroke-width="1.5"
                >
                    <ellipse
                        :cx="pose.leftHand.x"
                        :cy="pose.leftHand.y"
                        rx="12"
                        ry="11"
                    />
                    <ellipse
                        :cx="pose.rightHand.x"
                        :cy="pose.rightHand.y"
                        rx="12"
                        ry="11"
                    />
                </g>
                <g
                    stroke="#879991"
                    stroke-opacity=".17"
                    stroke-width="1.5"
                    stroke-linecap="round"
                >
                    <path
                        d="M171 349Q155 383 167 421M182 354L176 407M229 349Q245 383 233 421M218 354L224 407M169 451L167 488M231 451L233 488"
                    />
                    <path
                        :d="`M${pose.leftHand.x - 7} ${pose.leftHand.y}h12M${pose.rightHand.x - 5} ${pose.rightHand.y}h12`"
                    />
                </g>
            </svg>
            <figcaption>Bodybuilding</figcaption>
        </figure>

        <figure class="athlete-scene strength-scene" aria-hidden="true">
            <svg viewBox="0 0 400 600" fill="none" focusable="false">
                <defs>
                    <linearGradient
                        id="hero-lift-steel"
                        x1="0"
                        y1="0"
                        x2="1"
                        y2="0"
                    >
                        <stop stop-color="#111a1d" />
                        <stop offset=".35" stop-color="#4b5b5e" />
                        <stop offset=".55" stop-color="#283639" />
                        <stop offset="1" stop-color="#10191c" />
                    </linearGradient>
                    <linearGradient
                        id="hero-lift-body"
                        x1="90"
                        y1="200"
                        x2="270"
                        y2="400"
                        gradientUnits="userSpaceOnUse"
                    >
                        <stop stop-color="#3c4a4d" />
                        <stop offset=".42" stop-color="#263337" />
                        <stop offset="1" stop-color="#111b1e" />
                    </linearGradient>
                    <linearGradient id="hero-lift-shirt">
                        <stop stop-color="#263337" />
                        <stop offset=".5" stop-color="#111b1f" />
                        <stop offset="1" stop-color="#080f13" />
                    </linearGradient>
                    <linearGradient id="hero-lift-red">
                        <stop stop-color="#1b090e" />
                        <stop offset=".2" stop-color="#692832" />
                        <stop offset=".45" stop-color="#42141f" />
                        <stop offset=".85" stop-color="#270c13" />
                        <stop offset="1" stop-color="#14070b" />
                    </linearGradient>
                    <radialGradient id="hero-lift-glow">
                        <stop stop-color="#b43d48" stop-opacity=".33" />
                        <stop
                            offset=".55"
                            stop-color="#801f2f"
                            stop-opacity=".15"
                        />
                        <stop
                            offset="1"
                            stop-color="#5d1727"
                            stop-opacity="0"
                        />
                    </radialGradient>
                    <radialGradient id="hero-lift-plate-glow">
                        <stop stop-color="#c84255" stop-opacity=".24" />
                        <stop
                            offset=".5"
                            stop-color="#851e32"
                            stop-opacity=".1"
                        />
                        <stop
                            offset="1"
                            stop-color="#851e32"
                            stop-opacity="0"
                        />
                    </radialGradient>
                </defs>
                <ellipse
                    cx="200"
                    cy="315"
                    rx="190"
                    ry="240"
                    fill="url(#hero-lift-glow)"
                    class="squat-red-light"
                />
                <g
                    class="power-rack"
                    fill="url(#hero-lift-steel)"
                    stroke="#6e8280"
                    stroke-opacity=".2"
                    stroke-width="1.3"
                >
                    <path d="M70 58H330V70H70Z" />
                    <path
                        d="M70 58H81V530H70ZM319 58H330V530H319Z"
                        opacity=".48"
                    />
                    <path
                        d="M35 84L70 58H82L47 91ZM354 84L319 58H330L367 91Z"
                    />
                    <path d="M35 83H47V539H35ZM354 83H367V539H354Z" />
                    <path d="M25 534H101V546H25ZM309 534H382V546H309Z" />
                    <path
                        d="M43 520L78 507H121V516L88 530ZM322 507L362 520V530L319 517H280V507Z"
                        opacity=".65"
                    />
                    <path
                        d="M44 329H91V338H44ZM310 329H355V338H310Z"
                        opacity=".55"
                    />
                    <g fill="#070e11" stroke="none">
                        <circle
                            v-for="index in 13"
                            :key="`left-${index}`"
                            cx="41"
                            :cy="100 + index * 29"
                            r="2.2"
                        />
                        <circle
                            v-for="index in 13"
                            :key="`right-${index}`"
                            cx="360"
                            :cy="100 + index * 29"
                            r="2.2"
                        />
                    </g>
                </g>
                <ellipse
                    cx="207"
                    cy="551"
                    rx="124"
                    ry="14"
                    fill="#8fa8a1"
                    opacity=".035"
                />
                <g :transform="squat.bar" class="squat-barbell">
                    <g
                        fill="url(#hero-lift-plate-glow)"
                        class="squat-plate-light"
                    >
                        <ellipse cx="51" cy="0" rx="54" ry="92" />
                        <ellipse cx="349" cy="0" rx="54" ry="92" />
                    </g>
                    <rect
                        x="2"
                        y="-4"
                        width="396"
                        height="8"
                        rx="3"
                        fill="url(#hero-lift-steel)"
                        stroke="#879892"
                        stroke-opacity=".3"
                    />
                    <g
                        fill="url(#hero-lift-red)"
                        stroke="#89424b"
                        stroke-opacity=".45"
                        stroke-width="1.2"
                        class="squat-red-plates"
                    >
                        <g v-for="index in 5" :key="index">
                            <rect
                                :x="6 + (index - 1) * 18"
                                y="-49"
                                width="18"
                                height="98"
                                rx="5"
                            />
                            <rect
                                :x="376 - (index - 1) * 18"
                                y="-49"
                                width="18"
                                height="98"
                                rx="5"
                            />
                            <path
                                :d="`M${10 + (index - 1) * 18}-40v80M${390 - (index - 1) * 18}-40v80`"
                                stroke="#bc7376"
                                stroke-opacity=".25"
                            />
                        </g>
                    </g>
                    <path
                        d="M96-8h7V8h-7ZM297-8h7V8h-7Z"
                        fill="url(#hero-lift-steel)"
                    />
                </g>
                <g
                    fill="url(#hero-lift-body)"
                    stroke="#7b908b"
                    stroke-opacity=".22"
                    stroke-width="1.3"
                >
                    <path
                        :d="muscle(squat.leftHip, squat.leftKnee, 29, 21)"
                        class="squat-thigh"
                    />
                    <path
                        :d="muscle(squat.rightHip, squat.rightKnee, 29, 21)"
                        class="squat-thigh"
                    />
                    <path
                        :d="muscle(squat.leftKnee, squat.leftAnkle, 18, 10)"
                    />
                    <path
                        :d="muscle(squat.rightKnee, squat.rightAnkle, 18, 10)"
                    />
                    <path
                        :d="muscle(squat.leftShoulder, squat.leftElbow, 19, 15)"
                    />
                    <path
                        :d="
                            muscle(
                                squat.rightShoulder,
                                squat.rightElbow,
                                19,
                                15,
                            )
                        "
                    />
                    <path :d="muscle(squat.leftElbow, squat.leftHand, 13, 9)" />
                    <path
                        :d="muscle(squat.rightElbow, squat.rightHand, 13, 9)"
                    />
                </g>
                <g fill="#0c1519" stroke="#5c706c" stroke-opacity=".24">
                    <ellipse
                        :cx="squat.leftKnee.x"
                        :cy="squat.leftKnee.y"
                        class="squat-knee"
                        rx="20"
                        ry="23"
                    />
                    <ellipse
                        :cx="squat.rightKnee.x"
                        :cy="squat.rightKnee.y"
                        class="squat-knee"
                        rx="20"
                        ry="23"
                    />
                    <path :d="squat.shorts" />
                </g>
                <path
                    :d="squat.torso"
                    fill="url(#hero-lift-shirt)"
                    stroke="#7c928b"
                    stroke-opacity=".26"
                    stroke-width="1.5"
                    class="squat-torso"
                />
                <path
                    :d="`M${squat.hip.x - 31} ${squat.hip.y - 29}Q${squat.hip.x} ${squat.hip.y - 20} ${squat.hip.x + 32} ${squat.hip.y - 29}V${squat.hip.y - 12}Q${squat.hip.x} ${squat.hip.y - 3} ${squat.hip.x - 31} ${squat.hip.y - 12}Z`"
                    fill="#080f12"
                    stroke="#73817b"
                    stroke-opacity=".3"
                />
                <rect
                    :x="squat.hip.x - 6"
                    :y="squat.hip.y - 24"
                    width="12"
                    height="12"
                    rx="2"
                    fill="url(#hero-lift-steel)"
                    class="squat-buckle"
                />
                <path
                    :d="`M${squat.shoulder.x - 13} ${squat.shoulder.y - 23}h26v26h-26Z`"
                    fill="url(#hero-lift-body)"
                />
                <g :transform="squat.head" class="squat-head">
                    <path
                        d="M-23-2Q-25-29 0-30Q25-28 23-2L20 20Q11 33 0 34Q-11 32-20 20Z"
                        fill="url(#hero-lift-body)"
                        stroke="#8a9b91"
                        stroke-opacity=".22"
                        stroke-width="1.5"
                    />
                    <path
                        d="M-23-6Q-23-31 0-32Q24-30 23-6L15-15Q-4-11-18-15Z"
                        fill="#0a1216"
                    />
                </g>
                <g
                    fill="url(#hero-lift-body)"
                    stroke="#7b908b"
                    stroke-opacity=".25"
                >
                    <ellipse
                        :cx="squat.leftHand.x"
                        :cy="squat.leftHand.y"
                        rx="11"
                        ry="9"
                    />
                    <ellipse
                        :cx="squat.rightHand.x"
                        :cy="squat.rightHand.y"
                        rx="11"
                        ry="9"
                    />
                </g>
                <g
                    class="squat-feet"
                    fill="#0b1317"
                    stroke="#7d9188"
                    stroke-opacity=".3"
                    stroke-width="1.3"
                >
                    <path
                        d="M140 515L163 515L168 534L177 540L174 547H127L124 541L137 532Z"
                    />
                    <path
                        d="M260 515L237 515L232 534L223 540L226 547H273L276 541L263 532Z"
                    />
                    <path
                        d="M129 541h43M228 541h43"
                        stroke="#93a299"
                        stroke-opacity=".35"
                    />
                </g>
            </svg>
            <figcaption>Powerlifting</figcaption>
        </figure>
    </div>
</template>

<style scoped>
.hero-athletes {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: clamp(4px, 1vw, 16px);
    min-width: 0;
    padding: 24px 16px 32px 0;
}
.athlete-scene {
    display: flex;
    flex-direction: column;
    justify-content: center;
    min-width: 0;
    margin: 0;
}
.athlete-scene svg {
    display: block;
    width: 100%;
    max-height: 560px;
    overflow: hidden;
}
.athlete-scene figcaption {
    margin-top: 4px;
    color: #acb6b0;
    font-size: 12px;
    font-weight: 500;
    text-align: center;
    letter-spacing: 0.04em;
}
.posing-scene figcaption {
    color: #c9b58d;
}
@media (max-width: 720px) {
    .hero-athletes {
        padding: 16px 8px 32px;
    }
    .athlete-scene figcaption {
        font-size: 11px;
    }
}
</style>
