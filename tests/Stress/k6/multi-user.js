import { check, fail, sleep } from 'k6'
import http from 'k6/http'

const baseUrl = (__ENV.BASE_URL || 'http://localhost:8080').replace(/\/$/, '')
const targetPath = __ENV.TARGET_PATH || '/dashboard'
const handlePrefix = __ENV.HANDLE_PREFIX || 'm2-'
const handleStart = Number(__ENV.HANDLE_START || 2)
const handleMod = Number(__ENV.HANDLE_MOD || 0)
const pause = Number(__ENV.SLEEP || 0)
const p95 = Number(__ENV.P95 || 5000)

export const options = {
    vus: Number(__ENV.VUS || 20),
    duration: __ENV.DURATION || '15s',
    thresholds: {
        http_req_failed: ['rate<0.01'],
        'http_req_duration{name:target}': [`p(95)<${p95}`],
    },
}

let loggedIn = false
let cookieHeader = ''

function handleForVu() {
    let offset = __VU - 1

    if (handleMod > 0) {
        offset %= handleMod
    }

    return handlePrefix + (handleStart + offset)
}

function login() {
    const loginPage = http.get(baseUrl + '/login', { tags: { name: 'login-page' } })
    const token = loginPage.body.match(/name="_token" value="([^"]+)"/)?.[1]

    if (!token) {
        fail('Unable to read the CSRF token from the login page.')
    }

    const response = http.post(
        baseUrl + '/login',
        {
            _token: token,
            handle: handleForVu(),
            password: 'password',
        },
        { tags: { name: 'login' }, redirects: 5 },
    )

    const ok = check(response, {
        'logged in': (r) => r.url.endsWith('/dashboard'),
    })

    if (!ok) {
        fail(`Unable to log in as ${handleForVu()}.`)
    }

    const jar = http.cookieJar().cookiesForURL(baseUrl)

    cookieHeader = Object.entries(jar)
        .flatMap(([name, values]) => values.map((value) => `${name}=${value}`))
        .join('; ')
}

export default function () {
    if (!loggedIn) {
        login()
        loggedIn = true
    }

    const response = http.get(baseUrl + targetPath, {
        headers: {
            Accept: __ENV.ACCEPT || 'text/html',
            Cookie: cookieHeader,
        },
        tags: { name: 'target' },
        redirects: 0,
    })

    const targetOk = check(response, {
        'target responded': (r) => r.status === 200,
    })

    if (!targetOk) {
        console.info(
            JSON.stringify({
                vu: __VU,
                url: response.url,
                status: response.status,
                body: response.body ? response.body.slice(0, 200) : '',
            }),
        )
    }

    if (pause > 0) {
        sleep(pause)
    }
}
